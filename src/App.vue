<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { countByFilter, filteredItems, loadItems, selectItem, selectedItem, store } from './store'
import { getSettings } from './api'
import { generateFilePath } from '@nextcloud/router'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcAppNavigationSearch from '@nextcloud/vue/components/NcAppNavigationSearch'
import NcAppNavigationSpacer from '@nextcloud/vue/components/NcAppNavigationSpacer'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import ItemList from './components/ItemList.vue'
import ItemDetail from './components/ItemDetail.vue'
import AddItemDialog from './components/AddItemDialog.vue'
import SettingsView from './components/SettingsView.vue'

const coreIcon = (name: string): string => `url(/core/img/actions/${name}.svg)`

const appIcon = `url(${generateFilePath('televisorium', 'img', 'app.svg')})`
const tvIcon = `url(${generateFilePath('televisorium', 'img', 'tv.svg')})`

const filterOptions = [
	{ id: 'all', label: 'All titles', icon: appIcon },
	{ id: 'watchlist', label: 'Watchlist', icon: coreIcon('star') },
	{ id: 'watching', label: 'Watching', icon: coreIcon('play') },
	{ id: 'watched', label: 'Watched', icon: coreIcon('checkmark') },
	{ id: 'on_hold', label: 'On hold', icon: coreIcon('pause') },
	{ id: 'dropped', label: 'Dropped', icon: coreIcon('close') },
	{ id: 'movies', label: 'Movies', icon: coreIcon('video') },
	{ id: 'tv', label: 'TV shows', icon: tvIcon },
]
const settingsIcon = coreIcon('settings')

const iconStyle = (url: string): Record<string, string> => ({
	'-webkit-mask-image': url,
	'mask-image': url,
})

const addDialogOpen = ref(false)
const settingsView = ref(false)

const currentFilterLabel = computed(() => filterOptions.find((o) => o.id === store.filter)?.label ?? 'All titles')

onMounted(async () => {
	try {
		const settings = await getSettings()
		store.tmdbConfigured = settings.configured
		store.tmdbLanguage = settings.language
	} catch {
		store.tmdbConfigured = false
		store.tmdbLanguage = 'en-US'
	}
	await loadItems()
})

const setFilter = (id: string): void => {
	settingsView.value = false
	store.filter = id
	store.selectedId = null
	store.current = null
}

const openSettings = (): void => {
	settingsView.value = true
	store.selectedId = null
	store.current = null
}

const onSettingsSaved = async (): Promise<void> => {
	try {
		const settings = await getSettings()
		store.tmdbConfigured = settings.configured
		store.tmdbLanguage = settings.language
	} catch {
		store.tmdbConfigured = false
		store.tmdbLanguage = 'en-US'
	}
}

const onShowDetail = async (id: number): Promise<void> => {
	await selectItem(id)
}

const goBack = (): void => {
	settingsView.value = false
	store.selectedId = null
	store.current = null
}
</script>

<template>
	<NcContent app-name="televisorium">
		<NcAppNavigation>
			<template #list>
				<NcAppNavigationNew
					text="Add title"
					@click="addDialogOpen = true" />
				<NcAppNavigationSearch v-model="store.search" placeholder="Search titles" />
				<NcAppNavigationItem
					v-for="option in filterOptions"
					:key="option.id"
					:name="option.label"
					:active="store.filter === option.id"
					@click="setFilter(option.id)">
					<template #icon>
						<span :class="$style['nav-icon']" :style="iconStyle(option.icon)" />
					</template>
					<template v-if="countByFilter[option.id] > 0" #counter>
						<NcCounterBubble :count="countByFilter[option.id]" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationSpacer />
			</template>
			<template #footer>
				<NcAppNavigationItem
					name="TMDb settings"
					:active="settingsView"
					@click="openSettings()">
					<template #icon>
						<span :class="$style['nav-icon']" :style="iconStyle(settingsIcon)" />
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>

		<NcAppContent :class="$style.content">
			<SettingsView
				v-if="settingsView"
				:configured="store.tmdbConfigured"
				:language="store.tmdbLanguage"
				@saved="onSettingsSaved" />
			<ItemDetail
				v-else-if="selectedItem !== null"
				:item="selectedItem"
				@back="goBack" />
			<ItemList
				v-else
				:items="filteredItems"
				:loading="store.loading"
				:filter-label="currentFilterLabel"
				@select="onShowDetail" />
		</NcAppContent>

		<AddItemDialog
			:open="addDialogOpen"
			:tmdb-configured="store.tmdbConfigured"
			@update:open="addDialogOpen = $event"
			@added="loadItems"
			@open-settings="openSettings()" />
	</NcContent>
</template>

<style module>
.content {
	margin: 16px;
	width: 100%;
}

.nav-icon {
	width: 18px;
	height: 18px;
	-webkit-mask-size: contain;
	mask-size: contain;
	-webkit-mask-position: center;
	mask-position: center;
	-webkit-mask-repeat: no-repeat;
	mask-repeat: no-repeat;
	background-color: var(--color-text-maxcontrast);
}
</style>
