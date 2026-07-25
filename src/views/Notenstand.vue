<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { api } from '../api.ts'

interface AzubiListEintrag {
	userId: string
	displayName: string
	istAzubi: boolean
	azubi: { id: number, status: 'aktiv' | 'beendet' } | null
}
interface NotenZeile { datum: string, art: string, note: number, gewicht: number }
interface FachNoten { fachId: number, fachName: string, noten: NotenZeile[], schnitt: number | null }
interface Notenstand { lehrjahr: number, gueltigAb: string, faecher: FachNoten[] }

const NOTENART_LABEL: Record<string, string> = {
	schriftlich: 'Schriftlich',
	muendlich: 'Mündlich',
	stehgreif: 'Stegreifaufgabe',
}

const azubis = ref<AzubiListEintrag[]>([])
const aktiveAzubis = computed(() => azubis.value.filter((a) => a.istAzubi && a.azubi?.status === 'aktiv'))
const ausgewaehlterAzubi = ref<AzubiListEintrag | null>(null)
const notenstand = ref<Notenstand | null>(null)
const ladeFehler = ref('')
const detailFehler = ref('')

async function ladeAzubis() {
	ladeFehler.value = ''
	try {
		const { data } = await api.get<AzubiListEintrag[]>('/azubi')
		azubis.value = data
	} catch (e: any) {
		ladeFehler.value = e?.response?.data?.error ?? 'Azubi-Liste konnte nicht geladen werden.'
	}
}

async function waehleAzubi(eintrag: AzubiListEintrag) {
	ausgewaehlterAzubi.value = eintrag
	notenstand.value = null
	detailFehler.value = ''
	if (!eintrag.azubi) return
	try {
		const { data } = await api.get<Notenstand>(`/noten/${eintrag.azubi.id}`)
		notenstand.value = data
	} catch (e: any) {
		detailFehler.value = e?.response?.data?.error ?? 'Notenstand konnte nicht geladen werden.'
	}
}

function formatiereDatum(datum: string): string {
	return new Date(datum).toLocaleDateString('de-DE')
}

onMounted(ladeAzubis)
</script>

<template>
	<div class="notenstand">
		<h2>Notenstand</h2>

		<NcNoteCard v-if="ladeFehler" type="error">{{ ladeFehler }}</NcNoteCard>

		<div class="layout">
			<div class="azubi-liste">
				<NcButton
					v-for="a in aktiveAzubis"
					:key="a.userId"
					:type="ausgewaehlterAzubi?.userId === a.userId ? 'primary' : 'secondary'"
					class="azubi-eintrag"
					@click="waehleAzubi(a)">
					{{ a.displayName }}
				</NcButton>
				<p v-if="aktiveAzubis.length === 0" class="hinweis">Keine aktiven Azubis vorhanden.</p>
			</div>

			<div v-if="ausgewaehlterAzubi" class="detail">
				<NcNoteCard v-if="detailFehler" type="error">{{ detailFehler }}</NcNoteCard>

				<template v-if="notenstand">
					<h3>{{ ausgewaehlterAzubi.displayName }} — Lehrjahr {{ notenstand.lehrjahr }}</h3>
					<p class="hinweis">Zeitraum seit {{ formatiereDatum(notenstand.gueltigAb) }} — mündliche Noten und Stegreifaufgaben zählen nur 50% für den Notenschnitt.</p>

					<NcNoteCard v-if="notenstand.faecher.length === 0" type="info">
						Für dieses Lehrjahr sind keine Fächer hinterlegt.
					</NcNoteCard>

					<div v-for="fach in notenstand.faecher" :key="fach.fachId" class="fach-block">
						<h4>
							{{ fach.fachName }}
							<span class="schnitt">
								Notenschnitt: {{ fach.schnitt !== null ? fach.schnitt.toFixed(2) : 'keine Note' }}
							</span>
						</h4>
						<table v-if="fach.noten.length > 0" class="noten-tabelle">
							<thead>
								<tr>
									<th>Datum</th>
									<th>Art</th>
									<th>Note</th>
									<th>Gewichtung</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="(n, i) in fach.noten" :key="i">
									<td>{{ formatiereDatum(n.datum) }}</td>
									<td>{{ NOTENART_LABEL[n.art] ?? n.art }}</td>
									<td>{{ n.note }}</td>
									<td>{{ Math.round(n.gewicht * 100) }}%</td>
								</tr>
							</tbody>
						</table>
						<p v-else class="hinweis">Noch keine Noten in diesem Lehrjahr erfasst.</p>
					</div>
				</template>
			</div>
		</div>
	</div>
</template>

<style scoped>
.notenstand {
	max-width: 900px;
	margin: 16px;
}
.layout {
	display: flex;
	gap: 24px;
	align-items: flex-start;
}
.azubi-liste {
	display: flex;
	flex-direction: column;
	gap: 4px;
	flex: 0 0 220px;
}
.azubi-eintrag {
	justify-content: flex-start;
}
.detail {
	flex: 1 1 auto;
	min-width: 0;
}
.hinweis {
	color: var(--color-text-maxcontrast);
	margin-bottom: 8px;
}
.fach-block {
	margin-bottom: 20px;
}
.schnitt {
	font-weight: normal;
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
	margin-left: 8px;
}
.noten-tabelle {
	width: 100%;
	border-collapse: collapse;
}
.noten-tabelle th, .noten-tabelle td {
	text-align: left;
	padding: 4px 8px;
	border-bottom: 1px solid var(--color-border);
}
</style>
