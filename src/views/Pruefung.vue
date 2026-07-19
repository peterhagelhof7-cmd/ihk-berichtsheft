<script setup lang="ts">
import { onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { api } from '../api.ts'

interface Rueckweisung { kommentar: string, zurueckgewiesenAm: number }
interface FachEintragAnzeige { fachName: string, stunden: number, inhalt: string | null }
interface EintragAnzeige {
	datum: string
	tagTyp: string
	taetigkeit: string | null
	stunden: number | null
	faecher: FachEintragAnzeige[]
}
interface EingereichteWoche {
	id: number
	azubiId: number
	nachweisNr: number
	wocheVon: string
	wocheBis: string
	status: string
	eingereichtVonName: string
	eingereichtAm: number
	rueckweisungen: Rueckweisung[]
	eintraege: EintragAnzeige[]
}

const TAGTYP_LABEL: Record<string, string> = {
	betrieb: 'Betrieb',
	berufsschule: 'Berufsschule',
	feiertag: 'Feiertag',
	urlaub: 'Urlaub',
	krankheit: 'Krankheit',
}

const wochen = ref<EingereichteWoche[]>([])
const fehler = ref('')
const kommentarFuer = ref<Record<number, string>>({})

async function lade() {
	fehler.value = ''
	try {
		const { data } = await api.get<EingereichteWoche[]>('/pruefung')
		wochen.value = data
	} catch (e: any) {
		fehler.value = e?.response?.data?.error ?? 'Liste konnte nicht geladen werden.'
	}
}

async function akzeptieren(woche: EingereichteWoche) {
	fehler.value = ''
	try {
		await api.post(`/pruefung/${woche.id}/akzeptieren`)
		await lade()
	} catch (e: any) {
		fehler.value = e?.response?.data?.error ?? 'Akzeptieren fehlgeschlagen.'
	}
}

async function zurueckweisen(woche: EingereichteWoche) {
	const kommentar = kommentarFuer.value[woche.id]?.trim()
	if (!kommentar) {
		fehler.value = 'Bitte einen Kommentar eintragen — er ist bei einer Zurückweisung Pflicht.'
		return
	}
	fehler.value = ''
	try {
		await api.post(`/pruefung/${woche.id}/zurueckweisen`, { kommentar })
		delete kommentarFuer.value[woche.id]
		await lade()
	} catch (e: any) {
		fehler.value = e?.response?.data?.error ?? 'Zurückweisen fehlgeschlagen.'
	}
}

onMounted(lade)
</script>

<template>
	<div class="pruefung">
		<h2>Eingereichte Wochen — Prüfung</h2>
		<p class="hinweis">
			Sichtbar für alle Ausbilder (Vertretungsfall bei Krankheit/Urlaub) —
			nicht nur für den jeweils zuständigen Berichtsheft-Verantwortlichen.
		</p>

		<NcNoteCard v-if="fehler" type="error">{{ fehler }}</NcNoteCard>
		<NcNoteCard v-if="wochen.length === 0" type="info">Aktuell keine Wochen zur Prüfung.</NcNoteCard>

		<div v-for="woche in wochen" :key="woche.id" class="woche-karte">
			<h3>{{ woche.eingereichtVonName }} — Nachweis Nr. {{ woche.nachweisNr }}</h3>
			<p>{{ woche.wocheVon }} bis {{ woche.wocheBis }}</p>

			<div v-if="woche.rueckweisungen.length" class="historie">
				<strong>Bisherige Zurückweisungen:</strong>
				<ul>
					<li v-for="(r, i) in woche.rueckweisungen" :key="i">{{ r.kommentar }}</li>
				</ul>
			</div>

			<div class="eintraege">
				<div v-for="e in woche.eintraege" :key="e.datum" class="eintrag-zeile">
					<strong>{{ e.datum }} — {{ TAGTYP_LABEL[e.tagTyp] ?? e.tagTyp }}</strong>
					<p v-if="e.tagTyp === 'betrieb'" class="taetigkeit">{{ e.taetigkeit }} ({{ e.stunden }}h)</p>
					<ul v-else-if="e.tagTyp === 'berufsschule'">
						<li v-for="(f, i) in e.faecher" :key="i">
							{{ f.fachName }}: {{ f.stunden }}h<span v-if="f.inhalt"> – {{ f.inhalt }}</span>
						</li>
					</ul>
				</div>
			</div>

			<NcTextArea
				:model-value="kommentarFuer[woche.id] ?? ''"
				placeholder="Kommentar (Pflichtfeld bei Zurückweisung)"
				@update:model-value="(v) => kommentarFuer[woche.id] = v" />

			<div class="aktionen">
				<NcButton type="primary" @click="akzeptieren(woche)">Akzeptieren</NcButton>
				<NcButton type="error" @click="zurueckweisen(woche)">Zurückweisen</NcButton>
			</div>
		</div>
	</div>
</template>

<style scoped>
.pruefung {
	max-width: 700px;
	margin: 16px auto;
}
.hinweis {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}
.woche-karte {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 12px;
	margin-bottom: 16px;
}
.historie {
	background: var(--color-background-hover);
	padding: 8px;
	border-radius: var(--border-radius);
	margin-bottom: 8px;
	font-size: 0.9em;
}
.eintraege {
	margin-bottom: 12px;
}
.eintrag-zeile {
	padding: 6px 0;
	border-bottom: 1px solid var(--color-border);
}
.eintrag-zeile:last-child {
	border-bottom: none;
}
.eintrag-zeile .taetigkeit {
	white-space: pre-wrap;
	margin: 4px 0 0;
}
.aktionen {
	display: flex;
	gap: 8px;
	margin-top: 8px;
}
</style>
