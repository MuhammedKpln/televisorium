<script setup lang="ts">
import type { Item } from '../api'
import { generateFilePath } from '@nextcloud/router'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ItemCard from './ItemCard.vue'

defineProps<{
	items: Item[]
	loading: boolean
	filterLabel: string
}>()

const emit = defineEmits<{(e: 'select', id: number): void}>()

const appIcon = generateFilePath('televisorium', 'img', 'app.svg')

const iconStyle = (path: string): Record<string, string> => ({
	'-webkit-mask-image': `url(${path})`,
	'mask-image': `url(${path})`,
})
</script>

<template>
	<div v-if="loading" class="loading">
		<NcLoadingIcon :size="48" />
	</div>

	<NcEmptyContent v-else-if="items.length === 0">
		<template #icon>
			<span class="icon-movie" :style="iconStyle(appIcon)" />
		</template>
		<template #name>
			No {{ filterLabel }} titles
		</template>
		<template #description>
			Use “Add title” in the sidebar to grow your library.
		</template>
	</NcEmptyContent>

	<div v-else class="grid">
		<ItemCard
			v-for="item in items"
			:key="item.id"
			:item="item"
			@select="(id: number) => emit('select', id)" />
	</div>
</template>

<style scoped>
.loading {
	display: flex;
	justify-content: center;
	padding: 64px;
}

.grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
	gap: 16px;
	padding: 16px;
	width: 100%;
}

.icon-movie {
	width: 40px;
	height: 40px;
	background: var(--color-background-dark);
	-webkit-mask-size: contain;
	mask-size: contain;
	-webkit-mask-position: center;
	mask-position: center;
	-webkit-mask-repeat: no-repeat;
	mask-repeat: no-repeat;
}
</style>
