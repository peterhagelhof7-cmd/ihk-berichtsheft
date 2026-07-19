<script setup lang="ts">
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'

interface Fach { id: number, name: string }
interface FachZeileWert { fachId: number | null, stunden: number | null, inhalt: string | null }

const props = defineProps<{
	modelValue: FachZeileWert
	verfuegbareFaecher: Fach[]
	bearbeitbar: boolean
}>()
const emit = defineEmits<{
	'update:modelValue': [FachZeileWert]
	remove: []
}>()

function setzeFach(fachId: number | null) {
	emit('update:modelValue', { ...props.modelValue, fachId })
}
function setzeStunden(stunden: string) {
	emit('update:modelValue', { ...props.modelValue, stunden: stunden === '' ? null : Number(stunden) })
}
function setzeInhalt(inhalt: string) {
	emit('update:modelValue', { ...props.modelValue, inhalt: inhalt === '' ? null : inhalt })
}
</script>

<template>
	<div class="fach-zeile">
		<div class="fach-zeile__kopf">
			<NcSelect
				class="fach-zeile__fach"
				:model-value="verfuegbareFaecher.find(f => f.id === modelValue.fachId) ?? null"
				:disabled="!bearbeitbar"
				:options="verfuegbareFaecher"
				label="name"
				placeholder="Fach waehlen"
				@update:model-value="(f) => setzeFach(f ? f.id : null)" />
			<NcTextField
				class="fach-zeile__stunden"
				:model-value="modelValue.stunden?.toString() ?? ''"
				type="number"
				:disabled="!bearbeitbar"
				placeholder="Std."
				@update:model-value="setzeStunden" />
			<NcButton v-if="bearbeitbar" class="fach-zeile__entfernen" @click="emit('remove')">Entfernen</NcButton>
		</div>
		<NcTextField
			:model-value="modelValue.inhalt ?? ''"
			:disabled="!bearbeitbar"
			placeholder="Unterrichtsinhalt (optional)"
			@update:model-value="setzeInhalt" />
	</div>
</template>

<style scoped>
.fach-zeile {
	margin-bottom: 8px;
}
.fach-zeile__kopf {
	display: flex;
	gap: 8px;
	align-items: center;
	margin-bottom: 4px;
}
.fach-zeile__fach {
	flex: 1 1 auto;
	min-width: 0;
}
.fach-zeile__stunden {
	flex: 0 0 80px;
}
.fach-zeile__entfernen {
	flex: 0 0 auto;
	white-space: nowrap;
}
</style>
