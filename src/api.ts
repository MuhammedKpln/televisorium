import axios, { isAxiosError } from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

axios.defaults.headers.common['OCS-APIRequest'] = 'true'

export interface Episode {
	id: number
	item_id: number
	season_number: number
	episode_number: number
	title: string | null
	runtime: number | null
	watched: boolean
	watched_seconds: number
	updated_at: number
}

export interface Item {
	id: number
	item_type: 'movie' | 'tv'
	title: string
	tmdb_id: number | null
	year: number | null
	runtime: number | null
	poster_url: string | null
	backdrop_url: string | null
	overview: string | null
	status: string
	rating: number | null
	watched_seconds: number
	created_at: number
	updated_at: number
	episodes?: Episode[]
}

export interface ItemInput {
	item_type: 'movie' | 'tv'
	title: string
	tmdb_id?: number | null
	year?: number | null
	runtime?: number | null
	poster_url?: string | null
	backdrop_url?: string | null
	overview?: string | null
	status?: string
}

export interface TmdbResult {
	tmdb_id: number
	item_type: 'movie' | 'tv'
	title: string
	year: number | null
	overview: string | null
	poster_url: string | null
	backdrop_url: string | null
	runtime: number | null
	seasons?: Array<{ season_number: number; episode_count: number }>
}

export interface TmdbSeasonEpisode {
	season_number: number
	episode_number: number
	title: string | null
	runtime: number | null
	tmdb_id?: number
}

const ocs = (path: string): string => generateOcsUrl(`/apps/televisorium${path}`)

const data = <T>(response: { data: unknown }): T => {
	const payload = response.data
	if (payload !== null && typeof payload === 'object' && 'ocs' in payload) {
		const ocsData = (payload as { ocs?: { data?: unknown } }).ocs?.data
		if (ocsData !== undefined) {
			return ocsData as T
		}
	}
	return payload as T
}

export const extractErrorMessage = (error: unknown, fallback: string): string => {
	if (isAxiosError(error)) {
		const body = error.response?.data as { message?: string, ocs?: { meta?: { message?: string }, data?: { message?: string } } } | undefined
		const message = body?.ocs?.meta?.message ?? body?.ocs?.data?.message ?? body?.message
		if (typeof message === 'string' && message !== '') {
			return message
		}
	}
	return error instanceof Error && error.message !== '' ? error.message : fallback
}

export const listItems = async (params: { type?: string, status?: string, search?: string } = {}): Promise<Item[]> => {
	const response = await axios.get(ocs('/items'), { params })
	return data<Item[]>(response)
}

export const getItem = async (id: number): Promise<Item> => {
	const response = await axios.get(ocs(`/items/${id}`))
	return data<Item>(response)
}

export const createItem = async (item: ItemInput): Promise<Item> => {
	const response = await axios.post(ocs('/items'), item)
	return data<Item>(response)
}

export const updateItem = async (id: number, patch: Record<string, unknown>): Promise<Item> => {
	const response = await axios.put(ocs(`/items/${id}`), patch)
	return data<Item>(response)
}

export const deleteItem = async (id: number): Promise<void> => {
	await axios.delete(ocs(`/items/${id}`))
}

export const createEpisode = async (itemId: number, episode: Partial<Episode>): Promise<Episode> => {
	const response = await axios.post(ocs(`/items/${itemId}/episodes`), episode)
	return data<Episode>(response)
}

export const bulkCreateEpisodes = async (itemId: number, episodes: TmdbSeasonEpisode[]): Promise<Episode[]> => {
	const response = await axios.post(ocs(`/items/${itemId}/episodes/bulk`), { episodes })
	return data<Episode[]>(response)
}

export const updateEpisode = async (id: number, patch: Record<string, unknown>): Promise<Episode> => {
	const response = await axios.put(ocs(`/episodes/${id}`), patch)
	return data<Episode>(response)
}

export const deleteEpisode = async (id: number): Promise<void> => {
	await axios.delete(ocs(`/episodes/${id}`))
}

export const searchTmdb = async (query: string): Promise<TmdbResult[]> => {
	const response = await axios.get(ocs('/search'), { params: { query } })
	return data<{ results: TmdbResult[] }>(response).results
}

export const tmdbDetails = async (itemType: string, tmdbId: number): Promise<TmdbResult> => {
	const response = await axios.get(ocs(`/details/${itemType}/${tmdbId}`))
	return data<TmdbResult>(response)
}

export const tmdbSeason = async (tmdbId: number, seasonNumber: number): Promise<TmdbSeasonEpisode[]> => {
	const response = await axios.get(ocs(`/season/${tmdbId}/${seasonNumber}`))
	return data<TmdbSeasonEpisode[]>(response)
}

export interface Settings {
	configured: boolean
	language: string
}

export interface SettingsPatch {
	apiKey?: string
	language?: string
}

export const getSettings = async (): Promise<Settings> => {
	const response = await axios.get(ocs('/settings'))
	return data<Settings>(response)
}

export const saveSettings = async (patch: SettingsPatch): Promise<Settings> => {
	const response = await axios.post(ocs('/settings'), patch)
	return data<Settings>(response)
}

export const clearApiKey = async (): Promise<Settings> => {
	const response = await axios.delete(ocs('/settings'))
	return data<Settings>(response)
}
