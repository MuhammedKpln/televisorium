<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { generateFilePath } from '@nextcloud/router'
import type { Episode, Item } from '../api'
import { STATUSES, isWatched, percentDone, typeLabel } from '../utils'
import * as api from '../api'
import * as store from '../store'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import ProgressControl from './ProgressControl.vue'

const props = defineProps<{
	item: Item
}>()

const emit = defineEmits<{(e: 'back'): void}>()

const statusOptions = Object.entries(STATUSES).map(([value, def]) => ({ value, label: def.label }))
const statusValue = computed(() => statusOptions.find((o) => o.value === props.item.status))

const tmdbSeasons = ref<Array<{ season_number: number, episode_count: number }>>([])
const loadingSeasons = ref(false)
const importingSeason = ref<number | null>(null)
const progressOpenEpisode = ref<number | null>(null)
const showAddEpisode = ref(false)
const newEpisode = ref({
	season_number: 1,
	episode_number: 1,
	title: '',
	runtime: '',
})

const movieDone = computed(() => isWatched(props.item))

const seasons = computed(() => {
	const map = new Map<number, Episode[]>()
	for (const episode of props.item.episodes ?? []) {
		const list = map.get(episode.season_number) ?? []
		list.push(episode)
		map.set(episode.season_number, list)
	}
	return Array.from(map.entries())
		.map(([season, episodes]) => ({ season, episodes }))
		.sort((a, b) => a.season - b.season)
})

const appIcon = generateFilePath('televisorium', 'img', 'app.svg')
const backIcon = generateFilePath('televisorium', 'img', 'back.svg')
const playIcon = generateFilePath('televisorium', 'img', 'play.svg')

const iconStyle = (path: string): Record<string, string> => ({
	'-webkit-mask-image': `url(${path})`,
	'mask-image': `url(${path})`,
})

const posterFallback = (event: Event): void => {
	(event.target as HTMLImageElement).src = appIcon
}

onMounted(async () => {
	if (props.item.item_type === 'tv' && props.item.tmdb_id !== null) {
		loadingSeasons.value = true
		try {
			const details = await api.tmdbDetails('tv', props.item.tmdb_id)
			tmdbSeasons.value = details.seasons ?? []
		} catch {
			tmdbSeasons.value = []
		} finally {
			loadingSeasons.value = false
		}
	}
})

const saveStatus = async (value: string): Promise<void> => {
	await store.updateItem(props.item.id, { status: value })
}

const saveRating = async (rating: number | null): Promise<void> => {
	await store.updateItem(props.item.id, { rating })
}

const saveMovieProgress = async (seconds: number): Promise<void> => {
	await store.updateItem(props.item.id, { watched_seconds: seconds })
}

const markMovieWatched = async (): Promise<void> => {
	await store.updateItem(props.item.id, {
		watched_seconds: (props.item.runtime ?? 0) * 60,
		status: 'watched',
	})
}

const toggleEpisodeWatched = async (episode: Episode, watched: boolean): Promise<void> => {
	await store.updateEpisode(episode.id, { watched })
}

const saveEpisodeProgress = async (episode: Episode, seconds: number): Promise<void> => {
	await store.updateEpisode(episode.id, { watched_seconds: seconds })
}

const importSeason = async (seasonNumber: number): Promise<void> => {
	if (props.item.tmdb_id === null) {
		return
	}
	importingSeason.value = seasonNumber
	try {
		const episodes = await api.tmdbSeason(props.item.tmdb_id, seasonNumber)
		if (episodes.length > 0) {
			await store.bulkImportEpisodes(props.item.id, episodes)
		}
	} finally {
		importingSeason.value = null
	}
}

const importAllSeasons = async (): Promise<void> => {
	for (const season of tmdbSeasons.value) {
		await importSeason(season.season_number)
	}
}

const addEpisode = async (): Promise<void> => {
	await api.createEpisode(props.item.id, {
		season_number: newEpisode.value.season_number,
		episode_number: newEpisode.value.episode_number,
		title: newEpisode.value.title,
		runtime: newEpisode.value.runtime !== '' ? Number(newEpisode.value.runtime) : undefined,
	})
	newEpisode.value.title = ''
	newEpisode.value.season_number = 1
	newEpisode.value.episode_number = 1
	newEpisode.value.runtime = ''
	if (store.store.current !== null) {
		store.store.current = await api.getItem(store.store.current.id)
	}
}

const removeEpisode = async (episode: Episode): Promise<void> => {
	await store.removeEpisode(episode.id)
}

const remove = async (): Promise<void> => {
	if (window.confirm(`Remove “${props.item.title}” from your library?`)) {
		await store.removeItem(props.item.id)
		emit('back')
	}
}
</script>

<template>
	<div v-if="item" class="detail">
		<div v-if="item.backdrop_url" class="backdrop" :style="{ backgroundImage: `url('${item.backdrop_url}')` }" />

		<div class="header">
			<NcButton variant="secondary" @click="emit('back')">
				<template #icon>
					<span class="detail-icon" :style="iconStyle(backIcon)" />
				</template>
				Back
			</NcButton>
		</div>

		<div class="main">
			<img
				v-if="item.poster_url"
				class="poster"
				:src="item.poster_url"
				:alt="item.title"
				@error="posterFallback">
			<div v-else class="poster placeholder">
				{{ item.title.charAt(0) }}
			</div>

			<div class="content">
				<h2 class="title">
					{{ item.title }}
				</h2>
				<div class="subtitles">
					<span>{{ typeLabel(item.item_type) }}</span>
					<span v-if="item.year !== null">{{ item.year }}</span>
					<span v-if="item.runtime !== null">{{ item.runtime }} min</span>
				</div>

				<p v-if="item.overview" class="overview">
					{{ item.overview }}
				</p>

				<div class="controls">
					<div class="control">
						<label class="control-label">Status</label>
						<NcSelect
							:model-value="statusValue"
							:options="statusOptions"
							label="label"
							:reduce="(option: { value: string }) => option.value"
							input-label="Status"
							@update:model-value="saveStatus" />
					</div>

					<div class="control">
						<label class="control-label">Rating</label>
						<div class="stars">
							<button
								v-for="n in 10"
								:key="n"
								class="star"
								type="button"
								:class="{ active: item.rating !== null && n <= item.rating }"
								@click="saveRating(item.rating === n ? null : n)">
								★
							</button>
							<span v-if="item.rating === null" class="no-rating">none</span>
						</div>
					</div>
				</div>

				<div v-if="item.item_type === 'movie'" class="progress-section">
					<div class="progress-heading">
						<h3>Watched progress</h3>
						<div class="progress-state" :class="{ done: movieDone }">
							{{ movieDone ? 'Completed' : `${percentDone(item.watched_seconds, item.runtime)}% watched` }}
						</div>
					</div>
					<ProgressControl
						:seconds="item.watched_seconds"
						:runtime="item.runtime"
						@change="saveMovieProgress" />
					<NcButton v-if="!movieDone && item.runtime !== null" variant="primary" @click="markMovieWatched">
						Mark as watched
					</NcButton>
				</div>
			</div>
		</div>

		<div v-if="item.item_type === 'tv'" class="episodes">
			<div class="episode-actions">
				<h3>Episodes</h3>
				<div class="episode-action-buttons">
					<NcButton
						v-if="props.item.tmdb_id !== null && tmdbSeasons.length > 0"
						variant="secondary"
						:disabled="importingSeason !== null"
						@click="importAllSeasons">
						Import all (TMDb)
					</NcButton>
					<NcButton variant="secondary" @click="showAddEpisode = !showAddEpisode">
						Add episode
					</NcButton>
				</div>
			</div>

			<div v-if="showAddEpisode" class="add-episode">
				<NcTextField v-model="newEpisode.season_number"
					label="Season"
					type="number"
					min="1" />
				<NcTextField v-model="newEpisode.episode_number"
					label="Episode"
					type="number"
					min="1" />
				<NcTextField v-model="newEpisode.title" label="Title" />
				<NcTextField v-model="newEpisode.runtime"
					label="Runtime (min)"
					type="number"
					min="0" />
				<NcButton variant="primary" @click="addEpisode">
					Save
				</NcButton>
			</div>

			<div v-if="loadingSeasons" class="loading">
				<NcLoadingIcon :size="32" />
			</div>

			<NcEmptyContent v-else-if="seasons.length === 0">
				<template #name>
					No episodes yet
				</template>
				<template #description>
					Import them from TMDb or add one manually.
				</template>
			</NcEmptyContent>

			<div v-for="season in seasons" :key="season.season" class="season">
				<div class="season-heading">
					<h4>Season {{ season.season }}</h4>
					<span class="season-stats">
						{{ season.episodes.filter((e) => e.watched).length }}/{{ season.episodes.length }} watched
					</span>
				</div>
				<div v-for="episode in season.episodes" :key="episode.id" class="episode">
					<NcCheckboxRadioSwitch
						:model-value="episode.watched"
						@update:model-value="(checked: boolean) => toggleEpisodeWatched(episode, checked)">
						<template #default>
							<span class="episode-title">
								{{ episode.season_number }}×{{ episode.episode_number }}
								{{ episode.title ?? `Episode ${episode.episode_number}` }}
							</span>
							<span v-if="episode.runtime" class="episode-runtime">· {{ episode.runtime }} min</span>
						</template>
					</NcCheckboxRadioSwitch>
					<div class="episode-right">
						<span
							v-if="!episode.watched && episode.watched_seconds > 0"
							:class="['episode-progress', { done: percentDone(episode.watched_seconds, episode.runtime) === 100 }]">
							{{ percentDone(episode.watched_seconds, episode.runtime) }}%
						</span>
						<NcButton
							variant="tertiary-no-background"
							:aria-label="progressOpenEpisode === episode.id ? 'Hide progress' : 'Track progress'"
							@click="progressOpenEpisode = progressOpenEpisode === episode.id ? null : episode.id">
							<template #icon>
								<span class="detail-icon" :style="iconStyle(playIcon)" />
							</template>
						</NcButton>
						<NcButton variant="tertiary-no-background" aria-label="Delete episode" @click="removeEpisode(episode)">
							<template #icon>
								<span class="detail-icon" :style="iconStyle('/core/img/actions/delete.svg')" />
							</template>
						</NcButton>
					</div>
					<div v-if="progressOpenEpisode === episode.id" class="episode-progress-control">
						<ProgressControl
							:seconds="episode.watched_seconds"
							:runtime="episode.runtime"
							@change="(s: number) => saveEpisodeProgress(episode, s)" />
					</div>
				</div>
			</div>
		</div>

		<div class="footer">
			<NcButton variant="error" @click="remove">
				Remove from library
			</NcButton>
		</div>
	</div>
</template>

<style scoped>
.detail {
	width: 100%;
	max-width: 900px;
	margin: 0 auto;
	padding: 16px;
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.backdrop {
	position: absolute;
	top: 0;
	inset-inline: 0;
	height: 200px;
	background-size: cover;
	background-position: center 20%;
	opacity: 0.25;
	pointer-events: none;
}

.header {
	position: relative;
	z-index: 1;
}

.main {
	display: flex;
	gap: 20px;
	position: relative;
}

.poster {
	width: 200px;
	height: 300px;
	object-fit: cover;
	border-radius: 12px;
	flex-shrink: 0;
	box-shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
}

.placeholder {
	display: flex;
	align-items: center;
	justify-content: center;
	background: var(--color-background-dark);
	font-size: 64px;
	color: var(--color-text-maxcontrast);
}

.content {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 10px;
	min-width: 0;
}

.title {
	margin: 0;
	font-size: 28px;
}

.subtitles {
	display: flex;
	gap: 8px;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.overview {
	color: var(--color-text-secondary);
	line-height: 1.5;
}

.controls {
	display: flex;
	gap: 24px;
	flex-wrap: wrap;
}

.control {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 180px;
}

.control-label {
	font-size: 12px;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
}

.stars {
	display: flex;
	align-items: center;
	gap: 2px;
}

.star {
	background: none;
	border: none;
	font-size: 20px;
	color: var(--color-border-dark);
	cursor: pointer;
	padding: 0 1px;
}

.star.active {
	color: var(--color-primary-element);
}

.no-rating {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.progress-section {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-top: 8px;
	max-width: 520px;
}

.progress-heading {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
}

.progress-heading h3 {
	margin: 0;
	font-size: 16px;
}

.progress-state {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.progress-state.done {
	color: var(--color-success);
	font-weight: 600;
}

.episodes {
	display: flex;
	flex-direction: column;
	gap: 12px;
	border-top: 1px solid var(--color-border);
	padding-top: 12px;
}

.episode-actions {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 8px;
}

.episode-actions h3 {
	margin: 0;
}

.episode-action-buttons {
	display: flex;
	gap: 8px;
}

.add-episode {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	align-items: end;
}

.season {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.season-heading {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 4px;
}

.season-heading h4 {
	margin: 0;
}

.season-stats {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.episode {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
	padding: 8px 12px;
	background: var(--color-background-hover);
	border-radius: 8px;
}

.episode-title {
	display: inline-block;
	min-width: 0;
}

.episode-runtime {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.episode-right {
	display: flex;
	align-items: center;
	gap: 4px;
	margin-inline-start: auto;
}

.episode-progress {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.episode-progress.done {
	color: var(--color-success);
}

.episode-progress-control {
	width: 100%;
	padding: 8px 4px 4px;
}

.loading {
	display: flex;
	justify-content: center;
	padding: 24px;
}

.footer {
	border-top: 1px solid var(--color-border);
	padding-top: 12px;
}

.detail-icon {
	width: 18px;
	height: 18px;
	-webkit-mask-size: contain;
	mask-size: contain;
	-webkit-mask-position: center;
	mask-position: center;
	-webkit-mask-repeat: no-repeat;
	mask-repeat: no-repeat;
	background: var(--color-main-text);
}
</style>
