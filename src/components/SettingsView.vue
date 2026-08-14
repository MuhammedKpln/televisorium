<script setup lang="ts">
import { ref } from 'vue'
import * as api from '../api'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { generateFilePath } from '@nextcloud/router'

const props = defineProps<{
	configured: boolean
	language: string
}>()

const emit = defineEmits<{(e: 'saved'): void}>()

const apiKey = ref('')
const language = ref(props.language)
const loading = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const SAVE_ICON = '/core/img/actions/checkmark.svg'
const TRASH_ICON = '/core/img/actions/delete.svg'
const appIconPath = generateFilePath('televisorium', 'img', 'app.svg')

const languageOptions = [
	{ value: 'en-US', label: 'English' },
	{ value: 'de-DE', label: 'Deutsch' },
	{ value: 'es-ES', label: 'Español' },
	{ value: 'fr-FR', label: 'Français' },
	{ value: 'it-IT', label: 'Italiano' },
	{ value: 'ja-JP', label: '日本語' },
	{ value: 'ko-KR', label: '한국어' },
	{ value: 'nl-NL', label: 'Nederlands' },
	{ value: 'pl-PL', label: 'Polski' },
	{ value: 'pt-BR', label: 'Português (Brasil)' },
	{ value: 'pt-PT', label: 'Português (Portugal)' },
	{ value: 'ru-RU', label: 'Русский' },
	{ value: 'sv-SE', label: 'Svenska' },
	{ value: 'tr-TR', label: 'Türkçe' },
	{ value: 'zh-CN', label: '简体中文' },
	{ value: 'zh-TW', label: '繁體中文' },
	{ value: 'ar-SA', label: 'العربية' },
	{ value: 'hi-IN', label: 'हिन्दी' },
]

const iconStyle = (path: string): Record<string, string> => ({
	'-webkit-mask-image': `url(${path})`,
	'mask-image': `url(${path})`,
})

const languageChanged = (): boolean => language.value !== props.language

const save = async (): Promise<void> => {
	loading.value = true
	error.value = null
	success.value = null
	try {
		const patch: api.SettingsPatch = { language: language.value }
		if (apiKey.value.trim() !== '') {
			patch.apiKey = apiKey.value.trim()
		}
		await api.saveSettings(patch)
		success.value = 'Settings saved. Metadata will be fetched in the selected language.'
		apiKey.value = ''
		emit('saved')
	} catch (err) {
		error.value = err instanceof Error ? err.message : 'Failed to save settings'
	} finally {
		loading.value = false
	}
}

const remove = async (): Promise<void> => {
	loading.value = true
	error.value = null
	success.value = null
	try {
		await api.clearApiKey()
		success.value = 'API key removed.'
		apiKey.value = ''
		emit('saved')
	} catch (err) {
		error.value = err instanceof Error ? err.message : 'Failed to remove API key'
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<div class="settings">
		<div class="header">
			<span class="app-icon" :style="iconStyle(appIconPath)" />
			<h2>TMDb settings</h2>
		</div>

		<p class="hint">
			Televisorium uses <a href="https://www.themoviedb.org/settings/api" target="_blank" rel="noreferrer noopener">The Movie Database</a>
			to fetch metadata. Create a free account and request an API key (v3 auth), then paste it below.
			The key is stored for your account only.
		</p>

		<div class="field">
			<label for="tmdb-language">Metadata language</label>
			<select id="tmdb-language" v-model="language" class="language-select">
				<option
					v-for="option in languageOptions"
					:key="option.value"
					:value="option.value">
					{{ option.label }} ({{ option.value }})
				</option>
			</select>
		</div>

		<NcTextField
			v-model="apiKey"
			label="TMDb API key (v3 auth)"
			placeholder="e.g. 0123456789abcdef0123456789abcdef" />

		<div v-if="error" class="message error">
			{{ error }}
		</div>
		<div v-if="success" class="message success">
			{{ success }}
		</div>

		<div class="actions">
			<NcButton
				variant="primary"
				:disabled="(apiKey.trim() === '' && !languageChanged()) || loading"
				:loading="loading"
				@click="save">
				<template #icon>
					<span class="nav-icon" :style="iconStyle(SAVE_ICON)" />
				</template>
				Save settings
			</NcButton>
			<NcButton
				v-if="configured"
				variant="tertiary"
				:disabled="loading"
				@click="remove">
				<template #icon>
					<span class="nav-icon" :style="iconStyle(TRASH_ICON)" />
				</template>
				Remove key
			</NcButton>
		</div>
	</div>
</template>

<style scoped>
.settings {
	display: flex;
	flex-direction: column;
	gap: 16px;
	max-width: 640px;
	padding: 24px;
}

.header {
	display: flex;
	align-items: center;
	gap: 12px;
}

.app-icon {
	width: 22px;
	height: 22px;
	-webkit-mask-size: contain;
	mask-size: contain;
	-webkit-mask-position: center;
	mask-position: center;
	-webkit-mask-repeat: no-repeat;
	mask-repeat: no-repeat;
	background-color: var(--color-text-maxcontrast);
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
	background-color: var(--color-main-text);
}

.header h2 {
	margin: 0;
}

.hint {
	color: var(--color-text-secondary);
	line-height: 1.5;
	margin: 0;
}

.field {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.field label {
	font-weight: 600;
}

.language-select {
	width: 100%;
	max-width: 320px;
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-element, 8px);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}

.message {
	font-size: 14px;
}

.error {
	color: var(--color-error);
}

.success {
	color: var(--color-success);
}

.actions {
	display: flex;
	gap: 8px;
}
</style>
