export const STATUSES = {
	watchlist: { label: 'Watchlist', color: '#8f8f8f' },
	watching: { label: 'Watching', color: '#00b8ff' },
	watched: { label: 'Watched', color: '#2eb82e' },
	on_hold: { label: 'On hold', color: '#f0a500' },
	dropped: { label: 'Dropped', color: '#e33e66' },
} as const

export type ItemStatus = keyof typeof STATUSES

export const STATUS_LABELS = Object.keys(STATUSES) as ItemStatus[]

export const typeLabel = (type: 'movie' | 'tv'): string => (type === 'movie' ? 'Movie' : 'TV show')

export const formatTimecode = (totalSeconds: number): string => {
	const seconds = Math.max(0, Math.floor(totalSeconds))
	const h = Math.floor(seconds / 3600)
	const m = Math.floor((seconds % 3600) / 60)
	const s = seconds % 60
	const pad = (n: number): string => String(n).padStart(2, '0')
	if (h > 0) {
		return `${h}:${pad(m)}:${pad(s)}`
	}
	return `${m}:${pad(s)}`
}

export const formatDuration = (totalSeconds: number): string => {
	const seconds = Math.max(0, Math.floor(totalSeconds))
	const h = Math.floor(seconds / 3600)
	const m = Math.floor((seconds % 3600) / 60)
	if (h > 0) {
		return `${h}h ${m}m`
	}
	if (m > 0) {
		return `${m}m`
	}
	return `${seconds}s`
}

export const percentDone = (watchedSeconds: number, runtimeMinutes: number | null): number => {
	if (runtimeMinutes === null || runtimeMinutes <= 0) {
		return watchedSeconds > 0 ? 1 : 0
	}
	const max = runtimeMinutes * 60
	return Math.min(100, Math.round((Math.max(0, watchedSeconds) / max) * 100))
}

export const isWatched = (item: { watched_seconds: number, runtime: number | null }): boolean => {
	if (item.runtime === null || item.runtime <= 0) {
		return item.watched_seconds > 0
	}
	return item.watched_seconds >= item.runtime * 60
}
