import { useCallback, useEffect, useRef, useState } from 'react'
import { api, errorMessage, type Board, type VoiceSettings } from './api'
import {
  highestFilledPickKey,
  pickKey,
  planOnClockAnnouncement,
  planPickAnnouncements,
  storedPointerApplies,
  type StoredPointer,
} from './announce'

// The announcer engine: decides what still needs saying, then says it via
// Amazon Polly (docs/voice-announce-spec.md §5.2).
//
// Everything here is deliberately fail-soft. This hook runs alongside the
// ordinary board poll on draft day, and no failure in it — a dead CDN, an
// expired Cognito pool, a browser that won't decode mp3 — may stop the board
// from rendering or the draft from proceeding. Errors surface as text on the
// page and the queue keeps moving.

// Pinned to the exact version the src/voice.html prototype was tuned against.
// A floating version would silently change the audio path the day before a
// live event; upgrade deliberately, not by accident.
const AWS_SDK_SRC = 'https://sdk.amazonaws.com/js/aws-sdk-2.1093.0.min.js'

interface PollyResult {
  AudioStream?: Uint8Array
}

interface AwsGlobal {
  config: { region: string; credentials: unknown }
  CognitoIdentityCredentials: new (opts: { IdentityPoolId: string }) => {
    get(callback: (err?: Error) => void): void
  }
  Polly: new (opts: { apiVersion: string }) => {
    synthesizeSpeech(
      params: { Text: string; OutputFormat: string; VoiceId: string; Engine: string },
      callback: (err: Error | null, data?: PollyResult) => void,
    ): void
  }
}

declare global {
  interface Window {
    AWS?: AwsGlobal
    webkitAudioContext?: typeof AudioContext
  }
}

/**
 * Injects the AWS SDK <script> on first use and caches the in-flight promise,
 * so it's fetched once and only on this route — the board and commish pages
 * never pay for it. Cleared on failure so a later announcement can retry.
 */
let sdkPromise: Promise<AwsGlobal> | null = null

function loadAwsSdk(): Promise<AwsGlobal> {
  if (window.AWS !== undefined) {
    return Promise.resolve(window.AWS)
  }

  sdkPromise ??= new Promise<AwsGlobal>((resolve, reject) => {
    const script = document.createElement('script')
    script.src = AWS_SDK_SRC
    script.async = true
    script.onload = () => {
      if (window.AWS !== undefined) {
        resolve(window.AWS)
      } else {
        sdkPromise = null
        reject(new Error('The AWS SDK loaded but did not register itself.'))
      }
    }
    script.onerror = () => {
      sdkPromise = null
      reject(new Error(`Could not load the AWS SDK from ${AWS_SDK_SRC} — check network access.`))
    }
    document.head.appendChild(script)
  })

  return sdkPromise
}

/** Where this browser left off, so a reload doesn't re-read the draft aloud. */
interface Pointer {
  season: number
  /** pickKey of the commish's configured floor, to detect that they moved it */
  floor: number
  /** pickKey of the last pick this browser has accounted for */
  last: number
  /** board.draftStartedAt this was recorded against — see storedPointerApplies */
  startedAt: number | null
}

function pointerStorageKey(season: number): string {
  return `wmffl.voice.pointer.${season}`
}

function readStoredPointer(season: number): StoredPointer | null {
  try {
    const raw = window.localStorage.getItem(pointerStorageKey(season))
    if (raw === null) {
      return null
    }

    const parsed: unknown = JSON.parse(raw)
    if (
      typeof parsed === 'object' &&
      parsed !== null &&
      typeof (parsed as { floor?: unknown }).floor === 'number' &&
      typeof (parsed as { last?: unknown }).last === 'number'
    ) {
      return parsed as StoredPointer
    }
  } catch {
    // Private mode, disabled storage, or a corrupt entry — the commish's
    // start round/pick is the fallback, which is exactly what it's for.
  }

  return null
}

function writeStoredPointer(pointer: Pointer): void {
  try {
    window.localStorage.setItem(
      pointerStorageKey(pointer.season),
      JSON.stringify({
        floor: pointer.floor,
        last: pointer.last,
        startedAt: pointer.startedAt,
      }),
    )
  } catch {
    // Non-fatal: we just lose the resume point on the next reload.
  }
}

export interface AnnouncerState {
  /** True once a user gesture has unlocked the AudioContext */
  audioReady: boolean
  /** Must be called from a click handler — browsers block audio otherwise */
  enableAudio: () => void
  /** Currently synthesizing or playing */
  speaking: boolean
  /** The text of the most recent announcement, for the on-screen readout */
  lastSpoken: string | null
  error: string | null
}

export function useAnnouncer(board: Board | null, settings: VoiceSettings | null): AnnouncerState {
  const [audioReady, setAudioReady] = useState(false)
  const [speaking, setSpeaking] = useState(false)
  const [lastSpoken, setLastSpoken] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const audioCtxRef = useRef<AudioContext | null>(null)
  const credentialsRef = useRef<unknown>(null)
  const drainingRef = useRef(false)
  const pointerRef = useRef<Pointer | null>(null)
  const onClockRef = useRef<number | null>(null)
  // The freshest board and floor the drain loop has seen, so its end-of-queue
  // re-check doesn't work from the snapshot the run started with.
  const boardRef = useRef<Board | null>(null)
  const floorRef = useRef<number>(0)

  // Latest settings for the drain loop, which reads voiceId/poolId/region at
  // synthesis time rather than closing over a stale render's copy. Declared
  // before the announcement effect so it's already fresh when that runs.
  const settingsRef = useRef<VoiceSettings | null>(settings)
  const audioReadyRef = useRef(audioReady)
  useEffect(() => {
    settingsRef.current = settings
    audioReadyRef.current = audioReady
  }, [settings, audioReady])

  // A new pool or region invalidates the cached credentials object.
  useEffect(() => {
    credentialsRef.current = null
  }, [settings?.poolId, settings?.region])

  /** Synthesize one utterance and play it to completion. */
  const speakOne = useCallback(async (text: string): Promise<void> => {
    const audioCtx = audioCtxRef.current
    const current = settingsRef.current

    if (audioCtx === null) {
      throw new Error('Audio has not been enabled on this page yet.')
    }
    if (current === null || current.poolId === null || current.region === null) {
      throw new Error(current?.configError ?? 'Voice is not configured on this server.')
    }

    const AWS = await loadAwsSdk()
    AWS.config.region = current.region

    if (credentialsRef.current === null) {
      const credentials = new AWS.CognitoIdentityCredentials({ IdentityPoolId: current.poolId })
      // Resolve them up front so a bad pool ID reports itself as a
      // credentials problem rather than an opaque Polly failure.
      await new Promise<void>((resolve, reject) => {
        credentials.get((err) => (err ? reject(err) : resolve()))
      })
      credentialsRef.current = credentials
    }
    AWS.config.credentials = credentialsRef.current

    const polly = new AWS.Polly({ apiVersion: '2016-06-10' })
    const audioStream = await new Promise<Uint8Array>((resolve, reject) => {
      polly.synthesizeSpeech(
        { Text: text, OutputFormat: 'mp3', VoiceId: current.voiceId, Engine: 'neural' },
        (err, data) => {
          if (err) {
            reject(err)
          } else if (data?.AudioStream) {
            resolve(data.AudioStream)
          } else {
            reject(new Error('Polly returned no audio stream.'))
          }
        },
      )
    })

    // Copy out of the SDK's view rather than passing `.buffer` straight in:
    // decodeAudioData detaches the buffer it's given, and the view may be a
    // window onto a larger allocation.
    const bytes = audioStream.buffer.slice(
      audioStream.byteOffset,
      audioStream.byteOffset + audioStream.byteLength,
    ) as ArrayBuffer

    const buffer = await audioCtx.decodeAudioData(bytes)

    await new Promise<void>((resolve) => {
      const source = audioCtx.createBufferSource()
      source.buffer = buffer
      source.connect(audioCtx.destination)
      source.onended = () => resolve()
      source.start(0)
    })
  }, [])

  /**
   * Consume every pick newer than the pointer and return its lines, advancing
   * the pointer past them.
   *
   * This is the *only* thing that advances the pointer, and every caller
   * speaks what it returns (or, on the deliberate silent path, is throwing
   * the lines away on purpose). Keeping those two steps together is what
   * stops a pick from being marked "handled" by one code path while the
   * lines it produced are dropped by another — which is exactly how picks
   * went silent while on-the-clock lines kept playing.
   */
  const takeNewPickLines = useCallback((board: Board | null): string[] => {
    const pointer = pointerRef.current
    if (board === null || pointer === null || board.season !== pointer.season) {
      return []
    }

    // The board can go backwards: the commish's Undo Pick reopens the last
    // slot, and the re-pick reuses the same round/pick. Roll the pointer back
    // with it, or that re-pick reads as "already handled" and is never
    // announced — picks go silent while on-the-clock lines carry on.
    const highest = highestFilledPickKey(board.picks)
    const floorFor = Math.max(pointer.floor - 1, highest ?? pointer.floor - 1)
    if (pointer.last > floorFor) {
      // Persisted straight away, not just when this call produces lines: the
      // undo itself announces nothing, and a reload in that window would
      // otherwise restore the stale, too-far-ahead pointer.
      pointer.last = floorFor
      writeStoredPointer(pointer)
    }

    const plan = planPickAnnouncements(board.picks, pointer.last)
    if (plan.lines.length === 0) {
      return []
    }

    pointer.last = plan.last
    writeStoredPointer(pointer)

    return plan.lines
  }, [])

  /** The on-the-clock line, if one is due against the freshest board. */
  const nextOnClockLine = useCallback((): string | null => {
    const current = boardRef.current
    if (current === null) {
      return null
    }

    const plan = planOnClockAnnouncement(current, floorRef.current, onClockRef.current)
    onClockRef.current = plan.onClockKey

    return plan.line
  }, [])

  /**
   * The announcing loop. One loop with one `finally`, rather than the
   * prototype's recursive callbacks — that shape stalled everything whenever
   * an error path forgot to clear the speaking flag.
   *
   * Order matters, and is the whole point:
   *   1. say every pick the board we already have says is new;
   *   2. finding none, re-fetch the board and look again — reading out a run
   *      of picks takes longer than the 5s poll, so more have usually landed;
   *   3. only when a *fresh* board has nothing new, say who's on the clock.
   *
   * Step 2 is why the announcer rolls straight from one pick into the next
   * instead of interleaving a stale "the X are on the clock" naming a team
   * that has already picked.
   */
  const drain = useCallback(async (): Promise<void> => {
    if (drainingRef.current) {
      return
    }

    drainingRef.current = true
    let spoke = false

    try {
      for (;;) {
        let lines = takeNewPickLines(boardRef.current)

        if (lines.length === 0) {
          try {
            const fresh = await api.board()
            boardRef.current = fresh
            lines = takeNewPickLines(fresh)
          } catch {
            // Offline or a blip: carry on with the board the poll gave us.
          }
        }

        if (lines.length === 0) {
          const line = nextOnClockLine()
          if (line === null) {
            break
          }
          lines = [line]
        }

        for (const line of lines) {
          if (!spoke) {
            spoke = true
            setSpeaking(true)
          }

          try {
            await speakOne(line)
            setLastSpoken(line)
            setError(null)
          } catch (err) {
            // One bad utterance must not silence the rest of the draft.
            setError(errorMessage(err))
          }
        }
      }
    } finally {
      drainingRef.current = false
      if (spoke) {
        setSpeaking(false)
      }
    }
  }, [speakOne, takeNewPickLines, nextOnClockLine])

  const enableAudio = useCallback(() => {
    try {
      const Ctor = window.AudioContext ?? window.webkitAudioContext
      if (Ctor === undefined) {
        throw new Error('This browser does not support the Web Audio API.')
      }

      const ctx = audioCtxRef.current ?? new Ctor()
      audioCtxRef.current = ctx
      void ctx.resume()

      // A short beep, played from inside the click that unlocked the context.
      // It doubles as the operator's proof that this tab's audio is actually
      // reaching the video call — the one failure the app cannot detect.
      const oscillator = ctx.createOscillator()
      const gain = ctx.createGain()
      gain.gain.setValueAtTime(0.15, ctx.currentTime)
      oscillator.type = 'sine'
      oscillator.frequency.setValueAtTime(440, ctx.currentTime)
      oscillator.connect(gain)
      gain.connect(ctx.destination)
      oscillator.start()
      oscillator.stop(ctx.currentTime + 0.4)

      setAudioReady(true)
      audioReadyRef.current = true
      setError(null)

      // Warm the SDK now so the first real pick isn't delayed by the fetch.
      loadAwsSdk().catch((err: unknown) => setError(errorMessage(err)))
    } catch (err) {
      setError(errorMessage(err))
    }
  }, [])

  // The per-poll pass: work out what is newly sayable, advance the pointer,
  // and enqueue. Runs on every board poll, since `board` is a fresh object
  // each time.
  useEffect(() => {
    if (board === null || settings === null) {
      return
    }

    const floor = pickKey(settings.startRound, settings.startPick)

    // (Re)establish the pointer on first run, when the season changes, when
    // the commish moves the floor, or when Start Draft re-stamps
    // draft.full.start — each of those makes what this browser remembered
    // no longer apply. See storedPointerApplies().
    const startedAt = board.draftStartedAt
    const existing = pointerRef.current
    let pointer: Pointer
    if (
      existing === null ||
      existing.season !== board.season ||
      existing.floor !== floor ||
      existing.startedAt !== startedAt
    ) {
      const stored = readStoredPointer(board.season)
      pointer = {
        season: board.season,
        floor,
        startedAt,
        last: storedPointerApplies(stored, floor, startedAt) ? (stored?.last ?? floor - 1) : floor - 1,
      }
      pointerRef.current = pointer
      onClockRef.current = null
    } else {
      pointer = existing
    }

    // Kept current so the drain loop's re-check works from the freshest
    // board, and updated even while announcements are off so it's right the
    // moment they're switched on.
    boardRef.current = board
    floorRef.current = floor

    if (!settings.enabled || !audioReadyRef.current) {
      // Deliberately consume and discard: the pointer keeps up while we're
      // silent, so switching announcements on mid-draft says the *next*
      // pick rather than reading the whole backlog, and a reload is quiet.
      //
      // This is the ONLY place lines are thrown away. Everywhere else the
      // pointer moves, the lines are spoken — otherwise a pick lands in the
      // gap between a poll and this check and is silently lost forever.
      takeNewPickLines(board)
      return
    }

    // No queueing here: drain reads the board itself, so there is exactly
    // one thing advancing the pointer. If it's already running it will pick
    // up the board we just stored on its next lap.
    void drain()
  }, [board, settings, drain, takeNewPickLines])

  return { audioReady, enableAudio, speaking, lastSpoken, error }
}
