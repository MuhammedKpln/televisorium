<script setup lang="ts">
import type { Item } from '../api'
import { generateFilePath } from '@nextcloud/router'
import { STATUSES, percentDone, typeLabel } from '../utils'

defineProps<{
	item: Item
}>()

const emit = defineEmits<{(e: 'select', id: number): void}>()

const appIcon = generateFilePath('televisorium', 'img', 'app.svg')

const posterFallback = (event: Event): void => {
	(event.target as HTMLImageElement).src = appIcon
}
</script>

<template>
	<button class="card" type="button" @click="emit('select', item.id)">
		<div class="poster-wrap">
			<img
				v-if="item.poster_url"
				class="poster"
				:src="item.poster_url"
				:alt="item.title"
				loading="lazy"
				@error="posterFallback">
			<div v-else class="poster poster-placeholder">
				<span>{{ item.title.charAt(0) }}</span>
			</div>
			<span class="badge" :style="{ background: STATUSES[item.status as keyof typeof STATUSES]?.color ?? '#8f8f8f' }">
				{{ STATUSES[item.status as keyof typeof STATUSES]?.label ?? item.status }}
			</span>
		</div>

		<div class="meta">
			<div class="title">
				{{ item.title }}
			</div>
			<div class="subtitle">
				{{ typeLabel(item.item_type) }}{{ item.year !== null ? ` · ${item.year}` : '' }}
			</div>
		</div>

		<div v-if="item.item_type === 'movie' && (item.watched_seconds > 0 || item.runtime !== null)" class="progress-row">
			<div class="progress-track">
				<div class="progress-fill" :style="{ width: `${percentDone(item.watched_seconds, item.runtime)}%` }" />
			</div>
			<span class="progress-text">{{ percentDone(item.watched_seconds, item.runtime) }}%</span>
		</div>
	</button>
</template>

<style scoped>
.card {
	display: flex;
	flex-direction: column;
	gap: 8px;
	width: 160px;
	padding: 0;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	overflow: hidden;
	cursor: pointer;
	text-align: start;
	color: var(--color-main-text);
}

.card:hover {
	border-color: var(--color-primary-element);
}

.poster-wrap {
	position: relative;
	width: 100%;
	aspect-ratio: 2 / 3;
	background: var(--color-background-dark);
}

.poster {
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
}

.poster-placeholder {
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 48px;
	color: var(--color-text-maxcontrast);
}

.badge {
	position: absolute;
	top: 8px;
	inset-inline-start: 8px;
	padding: 2px 8px;
	border-radius: 999px;
	color: #ffffff;
	font-size: 11px;
	font-weight: 600;
}

.meta {
	padding: 0 10px;
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.title {
	font-weight: 600;
	line-height: 1.25;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.subtitle {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.progress-row {
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 0 10px 10px;
}

.progress-track {
	flex: 1;
	height: 4px;
	background: var(--color-background-hover);
	border-radius: 2px;
	overflow: hidden;
}

.progress-fill {
	height: 100%;
	background: var(--color-primary);
}

.progress-text {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
}
</style>
