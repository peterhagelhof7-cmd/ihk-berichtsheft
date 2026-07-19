<script setup lang="ts">
import { onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { api } from '../api.ts'
import FaecherVerwaltung from './FaecherVerwaltung.vue'
import LehrjahrZuweisung from './LehrjahrZuweisung.vue'

// -- AO2020-Berufsliste (Plan Abschnitt 2), fest, aendert sich nicht --
const AUSBILDUNGSBERUFE = [
	{ id: 'fiae', label: 'Fachinformatiker/-in Anwendungsentwicklung' },
	{ id: 'fisi', label: 'Fachinformatiker/-in Systemintegration' },
	{ id: 'fidp', label: 'Fachinformatiker/-in Daten- und Prozessanalyse' },
	{ id: 'fidv', label: 'Fachinformatiker/-in Digitale Vernetzung' },
	{ id: 'kfitsm', label: 'Kaufmann/-frau fuer IT-System-Management' },
	{ id: 'kfdm', label: 'Kaufmann/-frau fuer Digitalisierungsmanagement' },
]

interface Stammdaten {
	ausbildungsbetriebName: string
	ausbildungsbetriebAdresse: string
	ausbildungsjahrStart: string
	ausbilderGruppe: string
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
	} | null
}

const stammdaten = ref<Stammdaten>({
	ausbildungsbetriebName: '',
	ausbildungsbetriebAdresse: '',
	ausbildungsjahrStart: '09-01',
	ausbilderGruppe: 'berichtsheft-ausbilder',
})
const stammdatenGespeichert = ref(false)
const nutzer = ref<AzubiListEintrag[]>([])
const ladeFehler = ref('')

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

async function ladeStammdaten() {
	try {
		const { data } = await api.get<Stammdaten>('/stammdaten')
		stammdaten.value = data
	} catch (e) {
		ladeFehler.value = 'Stammdaten konnten nicht geladen werden.'
	}
}

async function speichereStammdaten() {
	await api.put('/stammdaten', stammdaten.value)
	stammdatenGespeichert.value = true
	setTimeout(() => { stammdatenGespeichert.value = false }, 3000)
}

async function ladeNutzer() {
	try {
		const { data } = await api.get<AzubiListEintrag[]>('/azubi')
		nutzer.value = data
	} catch (e) {
		ladeFehler.value = 'Benutzerliste konnte nicht geladen werden.'
	}
}

function starteAktivierung(eintrag: AzubiListEintrag) {
	aktivierenFuer.value = eintrag
	neuBeruf.value = AUSBILDUNGSBERUFE[0].id
	neuStart.value = new Date().toISOString().slice(0, 10)
	neuAusbildungsjahrStartWert.value = 1
	neuLehrjahrStartWert.value = 1
	neuVerantwortlicher.value = ''
	neuAbteilung.value = ''
}

async function aktiviereAzubi() {
	if (!aktivierenFuer.value) return
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
}

async function deckblattNeuErzeugen(azubiId: number) {
	await api.post(`/azubi/${azubiId}/deckblatt-neu-erzeugen`)
}

onMounted(() => {
	ladeStammdaten()
	ladeNutzer()
})
</script>

<template>
	<div class="berichtsheft-admin">
		<NcNoteCard v-if="ladeFehler" type="error">{{ ladeFehler }}</NcNoteCard>

		<h2>Betriebs-Stammdaten</h2>
		<p class="hinweis">
			Einmalig fuer den ganzen Betrieb - erscheint auf dem Deckblatt
			jedes Azubi-Berichtshefts.
		</p>
		<div class="formular">
			<NcTextField :value.sync="stammdaten.ausbildungsbetriebName" label="Ausbildungsbetrieb (rechtliche Firmierung)" />
			<NcTextField :value.sync="stammdaten.ausbildungsbetriebAdresse" label="Betriebsadresse" />
			<NcTextField :value.sync="stammdaten.ausbildungsjahrStart" label="Ausbildungsjahr-Start (MM-TT, z.B. 09-01)" />
			<NcTextField :value.sync="stammdaten.ausbilderGruppe" label="Nextcloud-Gruppenname fuer Ausbilder" />
			<NcButton type="primary" @click="speichereStammdaten">Speichern</NcButton>
			<NcNoteCard v-if="stammdatenGespeichert" type="success">Gespeichert.</NcNoteCard>
		</div>

		<h2>Azubi-Verwaltung</h2>
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
				<tr v-for="n in nutzer" :key="n.userId">
					<td>{{ n.displayName }} ({{ n.userId }})</td>
					<td>{{ n.istAzubi ? 'Azubi' : '-' }}</td>
					<td>{{ n.azubi?.ausbildungsberuf ?? '-' }}</td>
					<td>
						<NcButton v-if="!n.istAzubi" @click="starteAktivierung(n)">Als Azubi aktivieren</NcButton>
						<template v-else-if="n.azubi">
							<NcButton @click="lehrjahrFuerAzubiId = n.azubi.id">Lehrjahr</NcButton>
							<NcButton @click="deckblattNeuErzeugen(n.azubi.id)">Deckblatt neu erzeugen</NcButton>
						</template>
					</td>
				</tr>
			</tbody>
		</table>

		<div v-if="aktivierenFuer" class="aktivierungs-formular">
			<h3>{{ aktivierenFuer.displayName }} als Azubi aktivieren</h3>
			<NcSelect
				v-model="neuBeruf"
				:options="AUSBILDUNGSBERUFE.map(b => b.id)"
				:get-option-label="(id) => AUSBILDUNGSBERUFE.find(b => b.id === id)?.label ?? id"
				label="Ausbildungsberuf" />
			<NcTextField :value.sync="neuStart" type="date" label="Ausbildungsstart (bei diesem Betrieb)" />
			<NcTextField :value.sync="neuAusbildungsjahrStartWert" type="number" label="Ausbildungsjahr zu Beginn (Default 1, bei Betriebswechsel anpassen)" />
			<NcTextField :value.sync="neuLehrjahrStartWert" type="number" label="Lehrjahr zu Beginn" />
			<NcTextField :value.sync="neuVerantwortlicher" label="Berichtsheft-Verantwortliche/r (Nextcloud-Benutzer-ID eines Ausbilders)" />
			<NcTextField :value.sync="neuAbteilung" label="Ausbildungsabteilung (optional)" />
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
