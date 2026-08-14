import { computed, reactive } from 'vue'
import * as api from './api'

export const store = reactive({
	items: [] as api.Item[],
	current: null as api.Item | null,
	loading: false,
	selectedId: null as number | null,
	filter: 'all' as string,
	search: '',
	tmdbConfigured: false,
	tmdbLanguage: 'en-US',
})

export const selectedItem = computed(() => {
	if (store.selectedId === null) {
		return null
	}
	return store.current?.id === store.selectedId ? store.current : store.items.find((item) => item.id === store.selectedId) ?? null
})

export const filteredItems = computed(() => {
	const type = store.filter === 'movies' ? 'movie' : store.filter === 'tv' ? 'tv' : null
	const status = (['watchlist', 'watching', 'watched', 'on_hold', 'dropped'] as string[]).includes(store.filter)
		? store.filter
		: null

	let items = store.items
	if (type !== null) {
		items = items.filter((item) => item.item_type === type)
	}
	if (status !== null) {
		items = items.filter((item) => item.status === status)
	}
	if (store.search.trim() !== '') {
		const q = store.search.toLowerCase()
		items = items.filter((item) => item.title.toLowerCase().includes(q))
	}
	return items
})

export const countByFilter = computed(() => {
	const counts: Record<string, number> = { all: store.items.length, movies: 0, tv: 0 }
	for (const status of ['watchlist', 'watching', 'watched', 'on_hold', 'dropped']) {
		counts[status] = store.items.filter((item) => item.status === status).length
	}
	counts.movies = store.items.filter((item) => item.item_type === 'movie').length
	counts.tv = store.items.filter((item) => item.item_type === 'tv').length
	return counts
})

export async function loadItems(): Promise<void> {
	store.loading = true
	try {
		store.items = await api.listItems()
		store.current = store.current !== null ? await api.getItem(store.current.id) : null
	} finally {
		store.loading = false
	}
}

export async function selectItem(id: number): Promise<void> {
	store.selectedId = id
	store.current = await api.getItem(id)
}

export async function addItem(item: api.ItemInput): Promise<api.Item> {
	const created = await api.createItem(item)
	store.items.push(created)
	return created
}

export async function updateItem(id: number, patch: Record<string, unknown>): Promise<api.Item> {
	const updated = await api.updateItem(id, patch)
	const index = store.items.findIndex((item) => item.id === id)
	if (index !== -1) {
		store.items[index] = { ...store.items[index], ...updated }
	}
	if (store.current?.id === id) {
		store.current = updated
	}
	return updated
}

export async function removeItem(id: number): Promise<void> {
	await api.deleteItem(id)
	store.items = store.items.filter((item) => item.id !== id)
	if (store.selectedId === id) {
		store.selectedId = null
		store.current = null
	}
}

export async function bulkImportEpisodes(itemId: number, episodes: api.TmdbSeasonEpisode[]): Promise<void> {
	await api.bulkCreateEpisodes(itemId, episodes)
	if (store.current?.id === itemId) {
		store.current = await api.getItem(itemId)
	}
}

export async function updateEpisode(id: number, patch: Record<string, unknown>): Promise<void> {
	await api.updateEpisode(id, patch)
	if (store.current !== null) {
		store.current = await api.getItem(store.current.id)
		const index = store.items.findIndex((item) => item.id === store.current!.id)
		if (index !== -1) {
			store.items[index] = { ...store.items[index], ...store.current }
		}
	}
}

export async function removeEpisode(id: number): Promise<void> {
	await api.deleteEpisode(id)
	if (store.current !== null) {
		store.current = await api.getItem(store.current.id)
		const index = store.items.findIndex((item) => item.id === store.current!.id)
		if (index !== -1) {
			store.items[index] = { ...store.items[index], ...store.current }
		}
	}
}
