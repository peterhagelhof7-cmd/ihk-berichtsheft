<script setup lang="ts">
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'

interface Fach { id: number, name: string }
interface FachZeileWert { fachId: number | null, stunden: number | null, inhalt: string | null, noteArt: string | null, note: number | null }

const props = defineProps<{
	modelValue: FachZeileWert
	verfuegbareFaecher: Fach[]
	bearbeitbar: boolean
}>()
const emit = defineEmits<{
	'update:modelValue': [FachZeileWert]
	remove: []
}>()

const NOTENARTEN = [
	{ id: '', label: 'Keine Note' },
	{ id: 'schriftlich', label: 'Schriftlich' },
	{ id: 'muendlich', label: 'Mündlich (zählt 50%)' },
	{ id: 'stehgreif', label: 'Stegreifaufgabe (zählt 50%)' },
]
const NOTENWERTE = [1, 2, 3, 4, 5, 6].map((n) => ({ id: n, label: String(n) }))

function setzeFach(fachId: number | null) {
	emit('update:modelValue', { ...props.modelValue, fachId })
}
function setzeStunden(stunden: string) {
	emit('update:modelValue', { ...props.modelValue, stunden: stunden === '' ? null : Number(stunden) })
}
function setzeInhalt(inhalt: string) {
	emit('update:modelValue', { ...props.modelValue, inhalt: inhalt === '' ? null : inhalt })
}
function setzeNoteArt(noteArt: string | null) {
	emit('update:modelValue', {
		...props.modelValue,
		noteArt: noteArt ? noteArt : null,
		note: noteArt ? props.modelValue.note : null,
	})
}
function setzeNote(note: number | null) {
	emit('update:modelValue', { ...props.modelValue, note })
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
				placeholder="Fach wählen"
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
		<div class="fach-zeile__note">
			<NcSelect
				class="fach-zeile__note-art"
				:model-value="NOTENARTEN.find(n => n.id === (modelValue.noteArt ?? '')) ?? NOTENARTEN[0]"
				:disabled="!bearbeitbar"
				:options="NOTENARTEN"
				:clearable="false"
				label="label"
				@update:model-value="(n) => setzeNoteArt(n ? n.id : null)" />
			<NcSelect
				v-if="modelValue.noteArt"
				class="fach-zeile__note-wert"
				:model-value="NOTENWERTE.find(n => n.id === modelValue.note) ?? null"
				:disabled="!bearbeitbar"
				:options="NOTENWERTE"
				label="label"
				placeholder="Note"
				@update:model-value="(n) => setzeNote(n ? n.id : null)" />
		</div>
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
.fach-zeile__note {
	display: flex;
	gap: 8px;
	margin-top: 4px;
}
.fach-zeile__note-art {
	flex: 1 1 auto;
	min-width: 0;
}
.fach-zeile__note-wert {
	flex: 0 0 100px;
}
</style>
