<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { api } from '../api.ts'
import FaecherVerwaltung from './FaecherVerwaltung.vue'
import LehrjahrZuweisung from './LehrjahrZuweisung.vue'

// -- Berufsliste (AO2020-IT-Berufe + IT-System-Elektroniker/-in), fest --
const AUSBILDUNGSBERUFE = [
	{ id: 'fiae', label: 'Fachinformatiker/-in Anwendungsentwicklung' },
	{ id: 'fisi', label: 'Fachinformatiker/-in Systemintegration' },
	{ id: 'fidp', label: 'Fachinformatiker/-in Daten- und Prozessanalyse' },
	{ id: 'fidv', label: 'Fachinformatiker/-in Digitale Vernetzung' },
	{ id: 'kfitsm', label: 'Kaufmann/-frau für IT-System-Management' },
	{ id: 'kfdm', label: 'Kaufmann/-frau für Digitalisierungsmanagement' },
	{ id: 'itse', label: 'IT-System-Elektroniker/-in' },
]

interface Stammdaten {
	ausbildungsbetriebName: string
	ausbildungsbetriebAdresse: string
	ausbildungsjahrStart: string
	ausbilderGruppe: string
}

interface AusbilderListEintrag {
	userId: string
	displayName: string
}

interface AzubiListEintrag {
	userId: string
	displayName: string
	istAzubi: boolean
	azubi: {
		id: number
		userId: string
		displayName: string
		ausbildungsberuf: string
		ausbildungsstart: string
		ausbildungsjahrStartWert: number
		verantwortlicherAusbilderUserId: string
		ausbildungsabteilung: string | null
		status: 'aktiv' | 'beendet'
	} | null
}

const stammdaten = ref<Stammdaten>({
	ausbildungsbetriebName: '',
	ausbildungsbetriebAdresse: '',
	ausbildungsjahrStart: '09-01',
	ausbilderGruppe: 'berichtsheft-ausbilder',
})
const nutzer = ref<AzubiListEintrag[]>([])
const zeigeBeendete = ref(false)
const sichtbareNutzer = computed(() => zeigeBeendete.value
	? nutzer.value
	: nutzer.value.filter((n) => n.azubi?.status !== 'beendet'))
const ausbilderListe = ref<AusbilderListEintrag[]>([])
const ladeFehler = ref('')
const aktionsFehler = ref('')
const aktionsErfolg = ref('')
let erfolgTimeout: ReturnType<typeof setTimeout> | undefined

function zeigeErfolg(nachricht: string) {
	aktionsErfolg.value = nachricht
	clearTimeout(erfolgTimeout)
	erfolgTimeout = setTimeout(() => { aktionsErfolg.value = '' }, 3000)
}

function fehlermeldung(e: unknown, fallback: string): string {
	if (typeof e === 'object' && e !== null && 'response' in e) {
		const resp = (e as { response?: { data?: { error?: string } } }).response
		if (resp?.data?.error) {
			return resp.data.error
		}
	}
	return fallback
}

// Aktivierungsformular
const aktivierenFuer = ref<AzubiListEintrag | null>(null)
const neuBeruf = ref(AUSBILDUNGSBERUFE[0].id)
const neuStart = ref('')
const neuAusbildungsjahrStartWert = ref(1)
const neuLehrjahrStartWert = ref(1)
const neuVerantwortlicher = ref('')
const neuAbteilung = ref('')

// Lehrjahr-Zuweisungs-Dialog
const lehrjahrFuerAzubiId = ref<number | null>(null)

// Bearbeiten-Formular (Berufswechsel, Verantwortlicher, Abteilung)
const bearbeitenFuer = ref<AzubiListEintrag | null>(null)
const bearbeitenBeruf = ref('')
const bearbeitenVerantwortlicher = ref('')
const bearbeitenAbteilung = ref('')

async function ladeStammdaten() {
	try {
		const { data } = await api.get<Stammdaten>('/stammdaten')
		stammdaten.value = data
	} catch (e) {
		ladeFehler.value = 'Stammdaten konnten nicht geladen werden.'
	}
}

async function speichereStammdaten() {
	aktionsFehler.value = ''
	try {
		await api.put('/stammdaten', stammdaten.value)
		zeigeErfolg('Stammdaten gespeichert.')
	} catch (e) {
		aktionsFehler.value = fehlermeldung(e, 'Stammdaten konnten nicht gespeichert werden.')
	}
}

async function ladeNutzer() {
	try {
		const { data } = await api.get<AzubiListEintrag[]>('/azubi')
		nutzer.value = data
	} catch (e) {
		ladeFehler.value = 'Benutzerliste konnte nicht geladen werden.'
	}
}

async function ladeAusbilderListe() {
	try {
		const { data } = await api.get<AusbilderListEintrag[]>('/ausbilder')
		ausbilderListe.value = data
	} catch (e) {
		ladeFehler.value = 'Ausbilder-Liste konnte nicht geladen werden.'
	}
}

function starteAktivierung(eintrag: AzubiListEintrag) {
	aktivierenFuer.value = eintrag
	neuBeruf.value = AUSBILDUNGSBERUFE[0].id
	neuStart.value = new Date().toISOString().slice(0, 10)
	neuAusbildungsjahrStartWert.value = 1
	neuLehrjahrStartWert.value = 1
	neuVerantwortlicher.value = ausbilderListe.value[0]?.userId ?? ''
	neuAbteilung.value = ''
}

async function aktiviereAzubi() {
	if (!aktivierenFuer.value) return
	aktionsFehler.value = ''
	const name = aktivierenFuer.value.displayName
	try {
		await api.post(`/azubi/${aktivierenFuer.value.userId}/aktivieren`, {
			ausbildungsberuf: neuBeruf.value,
			ausbildungsstart: neuStart.value,
			ausbildungsjahrStartWert: neuAusbildungsjahrStartWert.value,
			lehrjahrStartWert: neuLehrjahrStartWert.value,
			verantwortlicherAusbilderUserId: neuVerantwortlicher.value,
			ausbildungsabteilung: neuAbteilung.value || null,
		})
		aktivierenFuer.value = null
		await ladeNutzer()
		zeigeErfolg(`${name} wurde als Azubi aktiviert.`)
	} catch (e) {
		aktionsFehler.value = fehlermeldung(e, 'Azubi konnte nicht aktiviert werden.')
	}
}

function starteBearbeitung(eintrag: AzubiListEintrag) {
	if (!eintrag.azubi) return
	bearbeitenFuer.value = eintrag
	bearbeitenBeruf.value = eintrag.azubi.ausbildungsberuf
	bearbeitenVerantwortlicher.value = eintrag.azubi.verantwortlicherAusbilderUserId
	bearbeitenAbteilung.value = eintrag.azubi.ausbildungsabteilung ?? ''
}

async function speichereBearbeitung() {
	if (!bearbeitenFuer.value?.azubi) return
	aktionsFehler.value = ''
	const name = bearbeitenFuer.value.displayName
	try {
		await api.put(`/azubi/${bearbeitenFuer.value.azubi.id}`, {
			ausbildungsberuf: bearbeitenBeruf.value,
			verantwortlicherAusbilderUserId: bearbeitenVerantwortlicher.value,
			ausbildungsabteilung: bearbeitenAbteilung.value || null,
		})
		bearbeitenFuer.value = null
		await ladeNutzer()
		zeigeErfolg(`${name} wurde aktualisiert.`)
	} catch (e) {
		aktionsFehler.value = fehlermeldung(e, 'Änderungen konnten nicht gespeichert werden.')
	}
}

async function beendeAusbildung(eintrag: AzubiListEintrag) {
	if (!eintrag.azubi) return
	aktionsFehler.value = ''
	try {
		await api.post(`/azubi/${eintrag.azubi.id}/beenden`)
		await ladeNutzer()
		zeigeErfolg(`Ausbildung von ${eintrag.displayName} wurde beendet.`)
	} catch (e) {
		aktionsFehler.value = fehlermeldung(e, 'Ausbildung konnte nicht beendet werden.')
	}
}

async function reaktiviereAzubi(eintrag: AzubiListEintrag) {
	if (!eintrag.azubi) return
	aktionsFehler.value = ''
	try {
		await api.post(`/azubi/${eintrag.azubi.id}/reaktivieren`)
		await ladeNutzer()
		zeigeErfolg(`${eintrag.displayName} wurde reaktiviert.`)
	} catch (e) {
		aktionsFehler.value = fehlermeldung(e, 'Azubi konnte nicht reaktiviert werden.')
	}
}

async function deckblattNeuErzeugen(azubiId: number) {
	aktionsFehler.value = ''
	try {
		await api.post(`/azubi/${azubiId}/deckblatt-neu-erzeugen`)
		zeigeErfolg('Deckblatt wurde neu erzeugt.')
	} catch (e) {
		aktionsFehler.value = fehlermeldung(e, 'Deckblatt konnte nicht neu erzeugt werden.')
	}
}

async function gesamtexportErzeugen(azubiId: number) {
	aktionsFehler.value = ''
	try {
		await api.post(`/azubi/${azubiId}/gesamtexport-erzeugen`)
		zeigeErfolg('IHK-Gesamtnachweis wurde erzeugt.')
	} catch (e) {
		aktionsFehler.value = fehlermeldung(e, 'Gesamtnachweis konnte nicht erzeugt werden.')
	}
}

onMounted(() => {
	ladeStammdaten()
	ladeNutzer()
	ladeAusbilderListe()
})
</script>

<template>
	<div class="berichtsheft-admin">
		<NcNoteCard v-if="ladeFehler" type="error">{{ ladeFehler }}</NcNoteCard>
		<NcNoteCard v-if="aktionsFehler" type="error">{{ aktionsFehler }}</NcNoteCard>
		<NcNoteCard v-if="aktionsErfolg" type="success">{{ aktionsErfolg }}</NcNoteCard>

		<h2>Betriebs-Stammdaten</h2>
		<p class="hinweis">
			Einmalig für den ganzen Betrieb – erscheint auf dem Deckblatt
			jedes Azubi-Berichtshefts.
		</p>
		<div class="formular">
			<NcTextField v-model="stammdaten.ausbildungsbetriebName" label="Ausbildungsbetrieb (rechtliche Firmierung)" />
			<NcTextField v-model="stammdaten.ausbildungsbetriebAdresse" label="Betriebsadresse" />
			<NcTextField v-model="stammdaten.ausbildungsjahrStart" label="Ausbildungsjahr-Start (MM-TT, z.B. 09-01)" />
			<NcTextField v-model="stammdaten.ausbilderGruppe" label="Nextcloud-Gruppenname für Ausbilder" />
			<NcButton type="primary" @click="speichereStammdaten">Speichern</NcButton>
		</div>

		<h2>Azubi-Verwaltung</h2>
		<NcCheckboxRadioSwitch v-model="zeigeBeendete">Auch Azubis mit beendeter Ausbildung anzeigen</NcCheckboxRadioSwitch>
		<table class="azubi-tabelle">
			<thead>
				<tr>
					<th>Benutzer</th>
					<th>Status</th>
					<th>Beruf</th>
					<th>Aktionen</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="n in sichtbareNutzer" :key="n.userId">
					<td>{{ n.displayName }} ({{ n.userId }})</td>
					<td>
						<span v-if="!n.istAzubi">-</span>
						<span v-else-if="n.azubi?.status === 'beendet'">Ausbildung beendet</span>
						<span v-else>Azubi</span>
					</td>
					<td>{{ n.azubi?.ausbildungsberuf ?? '-' }}</td>
					<td>
						<NcButton v-if="!n.istAzubi" @click="starteAktivierung(n)">Als Azubi aktivieren</NcButton>
						<template v-else-if="n.azubi?.status === 'beendet'">
							<NcButton @click="reaktiviereAzubi(n)">Reaktivieren</NcButton>
						</template>
						<template v-else-if="n.azubi">
							<NcButton @click="starteBearbeitung(n)">Bearbeiten</NcButton>
							<NcButton @click="lehrjahrFuerAzubiId = n.azubi.id">Lehrjahr</NcButton>
							<NcButton @click="deckblattNeuErzeugen(n.azubi.id)">Deckblatt neu erzeugen</NcButton>
							<NcButton @click="gesamtexportErzeugen(n.azubi.id)">IHK-Gesamtnachweis erzeugen</NcButton>
							<NcButton @click="beendeAusbildung(n)">Ausbildung beenden</NcButton>
						</template>
					</td>
				</tr>
			</tbody>
		</table>

		<div v-if="bearbeitenFuer" class="aktivierungs-formular">
			<h3>{{ bearbeitenFuer.displayName }} bearbeiten</h3>
			<NcSelect
				:model-value="AUSBILDUNGSBERUFE.find(b => b.id === bearbeitenBeruf) ?? null"
				:options="AUSBILDUNGSBERUFE"
				label="label"
				input-label="Ausbildungsberuf (z.B. bei Berufswechsel)"
				@update:model-value="(b) => { bearbeitenBeruf = b ? b.id : AUSBILDUNGSBERUFE[0].id }" />
			<NcSelect
				:model-value="ausbilderListe.find(a => a.userId === bearbeitenVerantwortlicher) ?? null"
				:options="ausbilderListe"
				label="displayName"
				input-label="Berichtsheft-Verantwortliche/r"
				@update:model-value="(a) => { bearbeitenVerantwortlicher = a ? a.userId : '' }" />
			<NcTextField v-model="bearbeitenAbteilung" label="Ausbildungsabteilung (optional)" />
			<NcButton type="primary" @click="speichereBearbeitung">Speichern</NcButton>
			<NcButton @click="bearbeitenFuer = null">Abbrechen</NcButton>
		</div>

		<div v-if="aktivierenFuer" class="aktivierungs-formular">
			<h3>{{ aktivierenFuer.displayName }} als Azubi aktivieren</h3>
			<NcSelect
				:model-value="AUSBILDUNGSBERUFE.find(b => b.id === neuBeruf) ?? null"
				:options="AUSBILDUNGSBERUFE"
				label="label"
				input-label="Ausbildungsberuf"
				@update:model-value="(b) => { neuBeruf = b ? b.id : AUSBILDUNGSBERUFE[0].id }" />
			<NcTextField v-model="neuStart" type="date" label="Ausbildungsstart (bei diesem Betrieb)" />
			<NcTextField v-model="neuAusbildungsjahrStartWert" type="number" label="Ausbildungsjahr zu Beginn (Default 1, bei Betriebswechsel anpassen)" />
			<NcTextField v-model="neuLehrjahrStartWert" type="number" label="Lehrjahr zu Beginn" />
			<NcSelect
				:model-value="ausbilderListe.find(a => a.userId === neuVerantwortlicher) ?? null"
				:options="ausbilderListe"
				label="displayName"
				input-label="Berichtsheft-Verantwortliche/r"
				@update:model-value="(a) => { neuVerantwortlicher = a ? a.userId : '' }" />
			<NcTextField v-model="neuAbteilung" label="Ausbildungsabteilung (optional)" />
			<NcButton type="primary" @click="aktiviereAzubi">Aktivieren</NcButton>
			<NcButton @click="aktivierenFuer = null">Abbrechen</NcButton>
		</div>

		<FaecherVerwaltung />

		<LehrjahrZuweisung
			v-if="lehrjahrFuerAzubiId !== null"
			:azubi-id="lehrjahrFuerAzubiId"
			@close="lehrjahrFuerAzubiId = null" />
	</div>
</template>

<style scoped>
.berichtsheft-admin {
	max-width: 900px;
	margin: 16px;
}
.formular, .aktivierungs-formular {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-width: 500px;
	margin-bottom: 24px;
}
.hinweis {
	color: var(--color-text-maxcontrast);
	margin-bottom: 8px;
}
.azubi-tabelle {
	width: 100%;
	border-collapse: collapse;
}
.azubi-tabelle th, .azubi-tabelle td {
	text-align: left;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
}
</style>
