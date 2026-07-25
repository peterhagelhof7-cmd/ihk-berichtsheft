<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import TagEintrag from '../components/TagEintrag.vue'
import { api } from '../api.ts'

interface Fach { id: number, name: string }
interface FachEintragWert { fachId: number | null, stunden: number | null, inhalt: string | null, noteArt: string | null, note: number | null }
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

// Anzeige-Labels fuer den intern gespeicherten Status-Wert (der Wert selbst
// bleibt ASCII, nur die Anzeige bekommt korrekte Umlaute/Grossschreibung).
const STATUS_LABEL: Record<string, string> = {
	offen: 'Offen',
	eingereicht: 'Eingereicht',
	akzeptiert: 'Akzeptiert',
	zurueckgewiesen: 'Zurückgewiesen',
}

function heutigesDatum(): string {
	const heute = new Date()
	const jahr = heute.getFullYear()
	const monat = String(heute.getMonth() + 1).padStart(2, '0')
	const tag = String(heute.getDate()).padStart(2, '0')
	return `${jahr}-${monat}-${tag}`
}
function montagVonHeute(): string {
	return datumPlus(heutigesDatum(), -((new Date().getDay() + 6) % 7))
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

/** ISO-8601-Kalenderwoche des uebergebenen Datums (yyyy-mm-dd). */
function kwVon(datum: string): number {
	const d = new Date(datum + 'T00:00:00Z')
	const tagVersatz = (d.getUTCDay() + 6) % 7
	d.setUTCDate(d.getUTCDate() - tagVersatz + 3)
	const donnerstagJahr = d.getUTCFullYear()
	const erstesDonnerstag = new Date(Date.UTC(donnerstagJahr, 0, 4))
	const ersterVersatz = (erstesDonnerstag.getUTCDay() + 6) % 7
	erstesDonnerstag.setUTCDate(erstesDonnerstag.getUTCDate() - ersterVersatz + 3)
	return 1 + Math.round((d.getTime() - erstesDonnerstag.getTime()) / (7 * 24 * 60 * 60 * 1000))
}
/** ISO-Wochenjahr des uebergebenen Datums - kann an Jahresgrenzen vom Kalenderjahr abweichen. */
function isoJahrVon(datum: string): number {
	const d = new Date(datum + 'T00:00:00Z')
	const tagVersatz = (d.getUTCDay() + 6) % 7
	d.setUTCDate(d.getUTCDate() - tagVersatz + 3)
	return d.getUTCFullYear()
}
/** Montag der KW $kw im ISO-Wochenjahr $isoJahr. */
function montagVonKW(isoJahr: number, kw: number): string {
	const erstesDonnerstag = new Date(Date.UTC(isoJahr, 0, 4))
	const ersterVersatz = (erstesDonnerstag.getUTCDay() + 6) % 7
	const montagKw1 = new Date(erstesDonnerstag)
	montagKw1.setUTCDate(erstesDonnerstag.getUTCDate() - ersterVersatz)
	const montag = new Date(montagKw1)
	montag.setUTCDate(montagKw1.getUTCDate() + (kw - 1) * 7)
	return montag.toISOString().slice(0, 10)
}
/** Anzahl KWs im ISO-Wochenjahr (52 oder 53) - der 28. Dezember liegt immer in der letzten KW. */
function anzahlKWImJahr(isoJahr: number): number {
	return kwVon(`${isoJahr}-12-28`)
}

const aktuellesIsoJahr = isoJahrVon(heutigesDatum())
const aktuelleKW = kwVon(heutigesDatum())

const kwListe = computed(() => {
	const anzahl = anzahlKWImJahr(aktuellesIsoJahr)
	return Array.from({ length: anzahl }, (_, i) => {
		const kw = i + 1
		return { kw, montag: montagVonKW(aktuellesIsoJahr, kw) }
	})
})

function kwWaehlen(montag: string) {
	wocheVon.value = montag
}

watch(wocheVon, lade)
onMounted(lade)
</script>

<template>
	<div class="wochenansicht">
		<div class="kw-raster">
			<button
				v-for="eintrag in kwListe"
				:key="eintrag.kw"
				type="button"
				class="kw-kaestchen"
				:class="{ 'kw-aktuell': eintrag.kw === aktuelleKW, 'kw-ausgewaehlt': eintrag.montag === wocheVon }"
				@click="kwWaehlen(eintrag.montag)">
				KW {{ eintrag.kw }}
			</button>
		</div>

		<div class="kopf">
			<h2>
				Woche vom {{ wocheVon }} bis {{ datumPlus(wocheVon, 6) }}
				<span v-if="woche"> — Nachweis Nr. {{ woche.nachweisNr }}, Ausbildungsjahr {{ ausbildungsjahr }}</span>
			</h2>
		</div>

		<NcNoteCard v-if="fehler" type="error">{{ fehler }}</NcNoteCard>
		<NcNoteCard v-if="meldung" type="success">{{ meldung }}</NcNoteCard>
		<NcNoteCard v-if="woche && woche.status !== 'offen'" type="info">
			Status: {{ STATUS_LABEL[woche.status] ?? woche.status }}
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
	margin-bottom: 16px;
}
.kw-raster {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(64px, 1fr));
	gap: 6px;
	margin-bottom: 24px;
}
.kw-kaestchen {
	padding: 8px 4px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	cursor: pointer;
	text-align: center;
	font-size: 0.9em;
}
.kw-kaestchen:hover {
	background: var(--color-background-hover);
}
.kw-kaestchen.kw-aktuell {
	font-weight: bold;
}
.kw-kaestchen.kw-ausgewaehlt {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	border-color: var(--color-primary-element);
}
.wochenend-schalter {
	display: flex;
	gap: 16px;
	margin-bottom: 16px;
}
</style>
