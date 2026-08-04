import { useState, type FormEvent } from 'react'
import { errorMessage } from '../api'
import { useSession } from '../session'

export function LoginCard() {
  const { login } = useSession()
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await login(username, password)
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="card">
      <h2 className="card-title">Log In</h2>
      <form className="login-form" onSubmit={onSubmit}>
        <label>
          User
          <input
            type="text"
            value={username}
            autoComplete="username"
            onChange={(e) => setUsername(e.target.value)}
          />
        </label>
        <label>
          Password
          <input
            type="password"
            value={password}
            autoComplete="current-password"
            onChange={(e) => setPassword(e.target.value)}
          />
        </label>
        {error !== null && <p className="error-text">{error}</p>}
        <button type="submit" className="btn btn-primary" disabled={busy || username === '' || password === ''}>
          {busy ? 'Logging in…' : 'Log In'}
        </button>
      </form>
    </section>
  )
}
