import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Kleiner gemeinsamer Wrapper um axios + generateUrl, damit die
 * einzelnen Views nicht jedes Mal den vollen Pfad
 * "/apps/berichtsheft/api/..." selbst zusammenbauen muessen.
 */
export function apiUrl(path: string): string {
	return generateUrl(`/apps/berichtsheft/api${path}`)
}

export const api = {
	get: <T = unknown>(path: string) => axios.get<T>(apiUrl(path)),
	post: <T = unknown>(path: string, data?: unknown) => axios.post<T>(apiUrl(path), data),
	put: <T = unknown>(path: string, data?: unknown) => axios.put<T>(apiUrl(path), data),
	delete: <T = unknown>(path: string) => axios.delete<T>(apiUrl(path)),
}
