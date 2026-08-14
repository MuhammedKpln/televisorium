<script setup lang="ts">
import { computed } from 'vue'
import { formatDuration, formatTimecode, percentDone } from '../utils'
import NcButton from '@nextcloud/vue/components/NcButton'

const props = defineProps<{
	seconds: number
	runtime: number | null
	disabled?: boolean
}>()

const emit = defineEmits<{(e: 'change', value: number): void}>()

const maxSeconds = computed(() => {
	if (props.runtime !== null && props.runtime > 0) {
		return props.runtime * 60
	}
	return 86400
})

const percent = computed(() => percentDone(props.seconds, props.runtime))

const clamp = (value: number): number => Math.max(0, Math.min(maxSeconds.value, Math.round(value)))

const onSlider = (event: Event): void => {
	emit('change', clamp((event.target as HTMLInputElement).valueAsNumber))
}

const onSecondsInput = (event: Event): void => {
	emit('change', clamp((event.target as HTMLInputElement).valueAsNumber))
}

const reset = (): void => {
	emit('change', 0)
}
</script>

<template>
	<div class="progress-control">
		<div class="row">
			<span class="timecode">{{ formatTimecode(seconds) }}</span>
			<span v-if="runtime !== null && runtime > 0" class="total">/ {{ formatDuration(runtime * 60) }}</span>
			<span v-else class="total">/ total</span>
		</div>

		<div class="track-wrap">
			<div class="track" :style="{ width: `${percent}%` }" />
			<input
				class="slider"
				type="range"
				min="0"
				:max="maxSeconds"
				:value="seconds"
				:disabled="disabled"
				step="1"
				@input="onSlider">
		</div>

		<div class="row actions">
			<label class="seconds-input">
				<span class="label">Position</span>
				<input
					type="number"
					min="0"
					:max="maxSeconds"
					:value="seconds"
					:disabled="disabled"
					step="1"
					@input="onSecondsInput">
				<span class="unit">seconds</span>
			</label>
			<NcButton
				variant="tertiary"
				:disabled="disabled || seconds === 0"
				@click="reset">
				{{ percent === 100 ? 'Re-watch' : 'Reset' }}
			</NcButton>
		</div>
	</div>
</template>

<style scoped>
.progress-control {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.row {
	display: flex;
	align-items: center;
	gap: 8px;
	position: relative;
}

.timecode {
	font-weight: 600;
	font-size: 16px;
}

.total {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.track-wrap {
	position: relative;
	display: flex;
	align-items: center;
	height: 28px;
}

.track {
	position: absolute;
	inset-inline-start: 0;
	top: 50%;
	transform: translateY(-50%);
	height: 4px;
	background: var(--color-primary);
	border-radius: 2px;
	pointer-events: none;
}

.slider {
	width: 100%;
	margin: 0;
	background: transparent;
	accent-color: var(--color-primary);
	z-index: 2;
}

.seconds-input {
	display: flex;
	align-items: center;
	gap: 6px;
}

.seconds-input .label,
.seconds-input .unit {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.seconds-input input {
	width: 90px;
	text-align: end;
}

.actions {
	justify-content: space-between;
}
</style>
