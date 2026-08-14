<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { TmdbResult } from '../api'
import * as api from '../api'
import { STATUS_LABELS, STATUSES } from '../utils'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'

const props = defineProps<{
	open: boolean
	tmdbConfigured: boolean
}>()

const emit = defineEmits<{(e: 'update:open', open: boolean): void, (e: 'added'): void, (e: 'open-settings'): void}>()

const onModalClose = (value: unknown): void => {
	emit('update:open', Boolean(value))
}

const query = ref('')
const results = ref<TmdbResult[]>([])
const searching = ref(false)
const searchError = ref<string | null>(null)
const addingId = ref<number | null>(null)
const manualMode = ref(false)

const statusOptions = STATUS_LABELS.map((status) => ({ value: status, label: `${STATUSES[status].label} (${status})` }))
const manual = ref<{
	item_type: 'movie' | 'tv'
	title: string
	year: string
	runtime: string
	poster_url: string
	overview: string
	status: string
}>({
	item_type: 'movie',
	title: '',
	year: '',
	runtime: '',
	poster_url: '',
	overview: '',
	status: 'watchlist',
})

watch(() => props.open, () => {
	if (props.open) {
		query.value = ''
		results.value = []
		searchError.value = null
		manualMode.value = false
	}
})

const doSearch = async (): Promise<void> => {
	const q = query.value.trim()
	if (q === '') {
		return
	}
	searching.value = true
	searchError.value = null
	try {
		results.value = await api.searchTmdb(q)
		if (results.value.length === 0) {
			searchError.value = 'No results found. Try a different title or add it manually.'
		}
	} catch (error) {
		searchError.value = error instanceof Error ? error.message : 'Search failed'
	} finally {
		searching.value = false
	}
}

const addFromTmdb = async (result: TmdbResult): Promise<void> => {
	addingId.value = result.tmdb_id
	try {
		let details = result
		if (result.item_type === 'tv') {
			details = await api.tmdbDetails('tv', result.tmdb_id)
		}
		const created = await api.createItem({
			item_type: result.item_type,
			title: details.title,
			tmdb_id: details.tmdb_id,
			year: details.year,
			runtime: details.runtime,
			poster_url: details.poster_url ?? '',
			backdrop_url: details.backdrop_url ?? '',
			overview: details.overview ?? '',
			status: 'watchlist',
		})

		if (result.item_type === 'tv' && (details.seasons?.length ?? 0) > 0) {
			for (const season of details.seasons ?? []) {
				const episodes = await api.tmdbSeason(details.tmdb_id, season.season_number)
				if (episodes.length > 0) {
					await api.bulkCreateEpisodes(created.id, episodes)
				}
			}
		}

		emit('added')
		emit('update:open', false)
	} catch (error) {
		searchError.value = error instanceof Error ? error.message : 'Failed to add title'
	} finally {
		addingId.value = null
	}
}

const addManually = async (): Promise<void> => {
	try {
		await api.createItem({
			item_type: manual.value.item_type,
			title: manual.value.title,
			tmdb_id: null,
			year: manual.value.year !== '' ? Number(manual.value.year) : null,
			runtime: manual.value.runtime !== '' ? Number(manual.value.runtime) : null,
			poster_url: manual.value.poster_url !== '' ? manual.value.poster_url : null,
			overview: manual.value.overview !== '' ? manual.value.overview : null,
			status: manual.value.status,
		})
		emit('added')
		emit('update:open', false)
	} catch (error) {
		searchError.value = error instanceof Error ? error.message : 'Failed to add title'
	}
}

const canAddManually = computed(() => manual.value.title.trim() !== '')

const typeOptions = [
	{ value: 'movie', label: 'Movie' },
	{ value: 'tv', label: 'TV show' },
]

const cardStyle = (result: TmdbResult): Record<string, string> => {
	const image = result.backdrop_url ?? result.poster_url
	return image !== null ? { backgroundImage: `url(${image})` } : {}
}
</script>

<template>
	<NcModal
		:show="open"
		size="large"
		@update:show="onModalClose">
		<div class="add-dialog">
			<h2>Add a title</h2>

			<div v-if="!tmdbConfigured" class="no-key">
				<p>TMDb search needs an API key. Add it in the settings to search for movies and TV shows, or add titles manually.</p>
				<NcButton variant="primary" @click="emit('open-settings')">
					Open settings
				</NcButton>
			</div>

			<template v-else>
				<div class="search-actions">
					<NcTextField
						v-model="query"
						label="Search TMDb"
						:show-trailing-button="query !== ''"
						@update:trailing-button-click="query = ''"
						@keyup.enter="doSearch" />
					<NcButton variant="primary" :disabled="query.trim() === '' || searching" @click="doSearch">
						Search
					</NcButton>
				</div>

				<div class="results">
					<NcLoadingIcon v-if="searching" :size="32" />
					<div v-else-if="results.length > 0" class="result-row">
						<div
							v-for="result in results"
							:key="`${result.item_type}-${result.tmdb_id}`"
							class="result-card"
							:style="cardStyle(result)">
							<div class="card-shade-bottom" />
							<div class="card-top">
								<span class="chip">{{ result.item_type === 'movie' ? 'Movie' : 'TV' }}</span>
								<span v-if="result.year" class="chip">{{ result.year }}</span>
							</div>
							<div class="card-title">
								{{ result.title }}
							</div>
							<div class="card-actions">
								<NcButton
									variant="primary"
									:disabled="addingId !== null"
									:loading="addingId === result.tmdb_id"
									@click="addFromTmdb(result)">
									Add
								</NcButton>
							</div>
						</div>
					</div>
				</div>
			</template>

			<div v-if="searchError" class="error">
				{{ searchError }}
			</div>

			<div class="manual-toggle">
				<NcButton variant="tertiary" :disabled="addingId !== null" @click="manualMode = !manualMode">
					{{ manualMode ? 'Back to search' : 'Add manually instead' }}
				</NcButton>
			</div>

			<div v-if="manualMode" class="manual">
				<NcTextField v-model="manual.title" label="Title *" required />
				<NcSelect v-model="manual.item_type"
					:options="typeOptions"
					label="label"
					:reduce="(o: { value: 'movie' | 'tv' }) => o.value"
					input-label="Type" />
				<NcTextField v-model="manual.year"
					label="Year"
					type="number"
					min="1888" />
				<NcTextField v-model="manual.runtime"
					label="Runtime (min)"
					type="number"
					min="0" />
				<NcTextField v-model="manual.poster_url" label="Poster URL" />
				<NcTextField v-model="manual.overview" label="Overview" />
				<NcSelect v-model="manual.status"
					:options="statusOptions"
					label="label"
					:reduce="(o: { value: string }) => o.value"
					input-label="Status" />
				<NcButton variant="primary" :disabled="!canAddManually || addingId !== null" @click="addManually">
					Save title
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<style scoped>
.add-dialog {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.add-dialog h2 {
	margin: 0;
}

.no-key {
	display: flex;
	flex-direction: column;
	gap: 12px;
	align-items: start;
}

.search-actions {
	display: flex;
	gap: 8px;
	align-items: end;
}

.search-actions :deep(.input-field) {
	flex: 1;
}

.results {
	min-height: 80px;
	display: flex;
	justify-content: center;
	align-items: center;
}

.result-row {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
	gap: 12px;
	padding: 4px 2px 12px;
	width: 100%;
}

.result-card {
	position: relative;
	aspect-ratio: 16 / 9;
	border-radius: 10px;
	background-color: #000;
	background-repeat: no-repeat;
	background-position: center;
	background-size: cover;
	overflow: hidden;
}

.card-shade-bottom {
	position: absolute;
	inset-block-end: 0;
	inset-inline: 0;
	height: 60%;
	background: linear-gradient(180deg, transparent, rgba(0, 0, 0, 0.75));
	pointer-events: none;
}

.card-top {
	position: absolute;
	inset-block-start: 8px;
	inset-inline: 8px;
	display: flex;
	gap: 6px;
	justify-content: flex-end;
}

.chip {
	background: rgba(0, 0, 0, 0.6);
	color: #fff;
	font-size: 11px;
	font-weight: 600;
	padding: 2px 8px;
	border-radius: 999px;
}

.card-title {
	position: absolute;
	inset-inline: 12px;
	inset-block-end: 12px;
	color: #fff;
	font-weight: 600;
	font-size: 15px;
	line-height: 1.25;
	text-shadow: 0 1px 3px rgba(0, 0, 0, 0.8);
	z-index: 1;
}

.card-actions {
	position: absolute;
	inset: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	background: rgba(0, 0, 0, 0.5);
	opacity: 0;
	transition: opacity 120ms ease;
}

.result-card:hover .card-actions,
.result-card:focus-within .card-actions {
	opacity: 1;
}

.manual {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px;
	align-items: end;
}

.manual :deep(.input-field),
.manual :deep(.vs__dropdown-toggle) {
	width: 100%;
}

.error {
	color: var(--color-error);
}

.manual-toggle {
	display: flex;
}
</style>
