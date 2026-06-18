import { getToken, clearSession } from './auth'
import router from '../router'

const BASE = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8080').replace(/\/$/, '')

/**
 * Wrapper su fetch: inietta il token JWT, gestisce JSON e i 401 (redirect login).
 */
async function request(method, path, body, options = {}) {
  const headers = { ...(options.headers || {}) }
  const token = getToken()
  if (token) headers['Authorization'] = `Bearer ${token}`

  let payload
  if (body !== undefined && !(body instanceof FormData)) {
    headers['Content-Type'] = 'application/json'
    payload = JSON.stringify(body)
  }

  const res = await fetch(`${BASE}${path}`, { method, headers, body: payload })

  if (res.status === 401) {
    clearSession()
    if (router.currentRoute.value.name !== 'login') {
      router.push({ name: 'login' })
    }
    throw new ApiError('Sessione scaduta. Effettua di nuovo il login.', 401)
  }

  // CSV o altri formati non-JSON.
  if (options.raw) {
    if (!res.ok) throw new ApiError('Richiesta fallita.', res.status)
    return res
  }

  const data = await res.json().catch(() => ({}))

  if (!res.ok || data.success === false) {
    throw new ApiError(data.message || 'Errore', res.status, data.errors || null)
  }

  return data
}

export class ApiError extends Error {
  constructor(message, status, errors = null) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

export const api = {
  base: BASE,
  get: (path, options) => request('GET', path, undefined, options),
  post: (path, body, options) => request('POST', path, body, options),
  put: (path, body, options) => request('PUT', path, body, options),
  del: (path, options) => request('DELETE', path, undefined, options),
}
