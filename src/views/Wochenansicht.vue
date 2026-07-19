<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import TagEintrag from '../components/TagEintrag.vue'
import { api } from '../api.ts'

interface Fach { id: number, name: string }
interface FachEintragWert { fachId: number | null, stunden: number | null }
interface EintragWert {
	tagTyp: string
	taetigkeit: string | null
	stunden: number | null
	faecher: FachEintragWert[]
}
interface WocheDaten {
	id: number
	nachweisNr: number
	wocheVon: string
	wocheBis: string
	status: string
	eingereichtAm: number | null
	akzeptiertAm: number | null
}

const TAGE_LABEL = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag']

function montagVonHeute(): string {
	const heute = new Date()
	const tagVersatz = (heute.getDay() + 6) % 7 // Mo=0..So=6
	heute.setDate(heute.getDate() - tagVersatz)
	return heute.toISOString().slice(0, 10)
}
function datumPlus(basis: string, tage: number): string {
	const d = new Date(basis)
	d.setDate(d.getDate() + tage)
	return d.toISOString().slice(0, 10)
}

const wocheVon = ref(montagVonHeute())
const woche = ref<WocheDaten | null>(null)
const eintraege = ref<Record<string, EintragWert>>({})
const verfuegbareFaecher = ref<Fach[]>([])
const ausbildungsjahr = ref<number | null>(null)
const samstagAktiv = ref(false)
const sonntagAktiv = ref(false)
const fehler = ref('')
const meldung = ref('')

const bearbeitbar = computed(() => woche.value?.status === 'offen' || woche.value?.status === 'zurueckgewiesen')

const anzeigeTage = computed(() => {
	const tage = [0, 1, 2, 3, 4]
	if (samstagAktiv.value) tage.push(5)
	if (sonntagAktiv.value) tage.push(6)
	return tage.map((i) => ({
		datum: datumPlus(wocheVon.value, i),
		label: TAGE_LABEL[i],
	}))
})

async function lade() {
	fehler.value = ''
	try {
		const { data } = await api.get<{
			woche: WocheDaten
			eintraege: Record<string, EintragWert>
			verfuegbareFaecher: Fach[]
			ausbildungsjahr: number
		}>(`/woche/${wocheVon.value}`)
		woche.value = data.woche
		eintraege.value = data.eintraege
		verfuegbareFaecher.value = data.verfuegbareFaecher
		ausbildungsjahr.value = data.ausbildungsjahr
		samstagAktiv.value = !!data.eintraege[datumPlus(wocheVon.value, 5)]
		sonntagAktiv.value = !!data.eintraege[datumPlus(wocheVon.value, 6)]
	} catch (e: any) {
		fehler.value = e?.response?.data?.error ?? 'Woche konnte nicht geladen werden.'
	}
}

async function eintragSpeichern(datum: string, wert: EintragWert) {
	try {
		await api.put(`/eintrag/${datum}`, wert)
		await lade()
	} catch (e: any) {
		fehler.value = e?.response?.data?.error ?? 'Eintrag konnte nicht gespeichert werden.'
	}
}

async function wocheEinreichen() {
	fehler.value = ''
	meldung.value = ''
	try {
		await api.post(`/woche/${wocheVon.value}/einreichen`, {
			samstagAktiv: samstagAktiv.value,
			sonntagAktiv: sonntagAktiv.value,
		})
		meldung.value = 'Woche eingereicht — der Berichtsheft-Verantwortliche wurde benachrichtigt.'
		await lade()
	} catch (e: any) {
		fehler.value = e?.response?.data?.error ?? 'Einreichen fehlgeschlagen.'
	}
}

function vorherigeWoche() {
	wocheVon.value = datumPlus(wocheVon.value, -7)
}
function naechsteWoche() {
	wocheVon.value = datumPlus(wocheVon.value, 7)
}

watch(wocheVon, lade)
onMounted(lade)
</script>

<template>
	<div class="wochenansicht">
		<div class="kopf">
			<NcButton @click="vorherigeWoche">← Vorherige Woche</NcButton>
			<h2>
				Woche vom {{ wocheVon }} bis {{ datumPlus(wocheVon, 6) }}
				<span v-if="woche"> — Nachweis Nr. {{ woche.nachweisNr }}, Ausbildungsjahr {{ ausbildungsjahr }}</span>
			</h2>
			<NcButton @click="naechsteWoche">Nächste Woche →</NcButton>
		</div>

		<NcNoteCard v-if="fehler" type="error">{{ fehler }}</NcNoteCard>
		<NcNoteCard v-if="meldung" type="success">{{ meldung }}</NcNoteCard>
		<NcNoteCard v-if="woche && woche.status !== 'offen'" type="info">
			Status: {{ woche.status }}
			<span v-if="woche.status === 'zurueckgewiesen'"> — bitte korrigieren und erneut einreichen.</span>
		</NcNoteCard>

		<div class="wochenend-schalter">
			<NcCheckboxRadioSwitch v-model="samstagAktiv" :disabled="!bearbeitbar">Samstag zuschalten</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-model="sonntagAktiv" :disabled="!bearbeitbar">Sonntag zuschalten</NcCheckboxRadioSwitch>
		</div>

		<TagEintrag
			v-for="tag in anzeigeTage"
			:key="tag.datum"
			:datum="tag.datum"
			:label="tag.label"
			:wert="eintraege[tag.datum] ?? null"
			:verfuegbare-faecher="verfuegbareFaecher"
			:bearbeitbar="bearbeitbar"
			@save="(wert) => eintragSpeichern(tag.datum, wert)" />

		<NcButton v-if="bearbeitbar" type="primary" @click="wocheEinreichen">Woche einreichen</NcButton>
	</div>
</template>

<style scoped>
.wochenansicht {
	max-width: 700px;
	margin: 16px auto;
}
.kopf {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 16px;
}
.wochenend-schalter {
	display: flex;
	gap: 16px;
	margin-bottom: 16px;
}
</style>
