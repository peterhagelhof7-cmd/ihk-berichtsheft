<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'
import FachZeile from './FachZeile.vue'

interface Fach { id: number, name: string }
interface FachEintragWert { fachId: number | null, stunden: number | null }
interface EintragWert {
	tagTyp: string
	taetigkeit: string | null
	stunden: number | null
	faecher: FachEintragWert[]
}

const props = defineProps<{
	datum: string
	label: string
	wert: EintragWert | null
	verfuegbareFaecher: Fach[]
	bearbeitbar: boolean
}>()
const emit = defineEmits<{ save: [EintragWert] }>()

const TAGTYPEN = [
	{ id: 'betrieb', label: 'Betrieb' },
	{ id: 'berufsschule', label: 'Berufsschule' },
	{ id: 'feiertag', label: 'Feiertag' },
	{ id: 'urlaub', label: 'Urlaub' },
	{ id: 'krankheit', label: 'Krankheit' },
]

const leer = (): EintragWert => ({ tagTyp: 'betrieb', taetigkeit: '', stunden: null, faecher: [] })
const lokal = ref<EintragWert>(props.wert ? { ...props.wert, faecher: [...props.wert.faecher] } : leer())

watch(() => props.wert, (neu) => {
	lokal.value = neu ? { ...neu, faecher: [...neu.faecher] } : leer()
})

const brauchtTaetigkeit = computed(() => lokal.value.tagTyp === 'betrieb')
const brauchtFaecher = computed(() => lokal.value.tagTyp === 'berufsschule')
const nurLabel = computed(() => ['feiertag', 'urlaub', 'krankheit'].includes(lokal.value.tagTyp))

function fachHinzufuegen() {
	lokal.value.faecher.push({ fachId: null, stunden: null })
}
function fachAktualisieren(index: number, wert: FachEintragWert) {
	lokal.value.faecher[index] = wert
}
function fachEntfernen(index: number) {
	lokal.value.faecher.splice(index, 1)
}

function speichern() {
	emit('save', { ...lokal.value })
}
</script>

<template>
	<div class="tag-eintrag" :class="{ gesperrt: !bearbeitbar }">
		<h4>{{ label }} — {{ datum }}</h4>

		<NcSelect
			v-model="lokal.tagTyp"
			:disabled="!bearbeitbar"
			:options="TAGTYPEN.map(t => t.id)"
			:get-option-label="(id) => TAGTYPEN.find(t => t.id === id)?.label ?? id" />

		<template v-if="brauchtTaetigkeit">
			<NcTextArea v-model="lokal.taetigkeit" :disabled="!bearbeitbar" placeholder="Ausgefuehrte Arbeiten, Unterweisungen, betrieblicher Unterricht, usw." />
			<NcTextField :value.sync="lokal.stunden" type="number" :disabled="!bearbeitbar" label="Stunden" />
		</template>

		<template v-else-if="brauchtFaecher">
			<FachZeile
				v-for="(fach, i) in lokal.faecher"
				:key="i"
				:model-value="fach"
				:verfuegbare-faecher="verfuegbareFaecher"
				@update:model-value="(w) => fachAktualisieren(i, w)"
				@remove="fachEntfernen(i)" />
			<NcButton v-if="bearbeitbar" @click="fachHinzufuegen">Fach hinzufuegen</NcButton>
		</template>

		<p v-else-if="nurLabel" class="hinweis">
			Kein Taetigkeitstext/Fach noetig — im PDF erscheint nur „{{ TAGTYPEN.find(t => t.id === lokal.tagTyp)?.label }}".
		</p>

		<NcButton v-if="bearbeitbar" type="primary" @click="speichern">Speichern</NcButton>
	</div>
</template>

<style scoped>
.tag-eintrag {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 12px;
	margin-bottom: 12px;
}
.tag-eintrag.gesperrt {
	opacity: 0.7;
}
.hinweis {
	color: var(--color-text-maxcontrast);
}
</style>
