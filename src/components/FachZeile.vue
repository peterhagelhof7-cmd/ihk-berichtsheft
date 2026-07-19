<script setup lang="ts">
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'

interface Fach { id: number, name: string }
interface FachZeileWert { fachId: number | null, stunden: number | null }

const props = defineProps<{
	modelValue: FachZeileWert
	verfuegbareFaecher: Fach[]
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
</script>

<template>
	<div class="fach-zeile">
		<NcSelect
			:model-value="modelValue.fachId"
			:options="verfuegbareFaecher.map(f => f.id)"
			:get-option-label="(id) => verfuegbareFaecher.find(f => f.id === id)?.name ?? '?'"
			placeholder="Fach waehlen"
			@update:model-value="setzeFach" />
		<NcTextField
			:value="modelValue.stunden?.toString() ?? ''"
			type="number"
			placeholder="Std."
			@update:value="setzeStunden" />
		<NcButton @click="emit('remove')">Entfernen</NcButton>
	</div>
</template>

<style scoped>
.fach-zeile {
	display: flex;
	gap: 8px;
	align-items: center;
	margin-bottom: 4px;
}
</style>
