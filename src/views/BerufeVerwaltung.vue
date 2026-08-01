<script setup lang="ts">
import { onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { api } from '../api.ts'

interface Beruf {
	id: number
	key: string
	bezeichnung: string
	fachrichtung: string
	label: string
}

const berufe = ref<Beruf[]>([])
const neuBezeichnung = ref('')
const neuFachrichtung = ref('')
const fehler = ref('')

async function lade() {
	const { data } = await api.get<Beruf[]>('/beruf')
	berufe.value = data
}

async function anlegen() {
	fehler.value = ''
	if (!neuBezeichnung.value.trim()) return
	try {
		await api.post('/beruf', {
			bezeichnung: neuBezeichnung.value,
			fachrichtung: neuFachrichtung.value,
		})
		neuBezeichnung.value = ''
		neuFachrichtung.value = ''
		await lade()
	} catch (e) {
		fehler.value = fehlertext(e)
	}
}

async function speichern(beruf: Beruf) {
	fehler.value = ''
	try {
		await api.put(`/beruf/${beruf.id}`, {
			bezeichnung: beruf.bezeichnung,
			fachrichtung: beruf.fachrichtung,
		})
		await lade()
	} catch (e) {
		fehler.value = fehlertext(e)
	}
}

async function loeschen(beruf: Beruf) {
	fehler.value = ''
	try {
		await api.delete(`/beruf/${beruf.id}`)
		await lade()
	} catch (e) {
		fehler.value = fehlertext(e)
	}
}

// Server-Fehlermeldung (z.B. "Beruf wird noch von N Azubi(s) verwendet")
// durchreichen, sonst eine generische Meldung.
function fehlertext(e: unknown): string {
	const resp = (e as { response?: { data?: { error?: string } } }).response
	return resp?.data?.error ?? 'Aktion fehlgeschlagen.'
}

onMounted(lade)
</script>

<template>
	<div class="berufe-verwaltung">
		<h2>Ausbildungsberufe</h2>
		<p class="hinweis">
			Berufe für das Deckblatt. Weitere Berufe (auch anderer Branchen)
			können hier angelegt werden. Die <em>Fachrichtung</em> ist optional
			und wird getrennt aufs Deckblatt gedruckt.
		</p>

		<p v-if="fehler" class="fehler">{{ fehler }}</p>

		<table class="berufe-tabelle">
			<thead>
				<tr>
					<th>Bezeichnung</th>
					<th>Fachrichtung (optional)</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="beruf in berufe" :key="beruf.id">
					<td><NcTextField v-model="beruf.bezeichnung" label="Bezeichnung" :show-trailing-button="false" /></td>
					<td><NcTextField v-model="beruf.fachrichtung" label="Fachrichtung" :show-trailing-button="false" /></td>
					<td class="aktionen">
						<NcButton type="primary" @click="speichern(beruf)">Speichern</NcButton>
						<NcButton @click="loeschen(beruf)">Löschen</NcButton>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="neu-formular">
			<NcTextField v-model="neuBezeichnung" label="Neuer Beruf (Bezeichnung)" />
			<NcTextField v-model="neuFachrichtung" label="Fachrichtung (optional)" />
			<NcButton type="primary" @click="anlegen">Beruf anlegen</NcButton>
		</div>
	</div>
</template>

<style scoped>
.berufe-verwaltung {
	margin-top: 24px;
}
.hinweis {
	color: var(--color-text-maxcontrast);
	margin-bottom: 8px;
}
.fehler {
	color: var(--color-error);
	margin-bottom: 8px;
}
.berufe-tabelle {
	width: 100%;
	border-collapse: collapse;
	margin-bottom: 16px;
}
.berufe-tabelle th, .berufe-tabelle td {
	text-align: left;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
	vertical-align: top;
}
.aktionen {
	display: flex;
	gap: 8px;
}
.neu-formular {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-width: 400px;
}
</style>
