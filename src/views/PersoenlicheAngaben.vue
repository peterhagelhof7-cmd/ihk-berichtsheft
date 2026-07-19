<script setup lang="ts">
import { ref } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { api } from '../api.ts'

interface AzubiDaten {
	vorname: string | null
	nachname: string | null
}
interface DigestPraeferenz {
	wochentag: number | null
	uhrzeitStunde: number | null
}

const azubiDaten = loadState<AzubiDaten | null>('berichtsheft', 'azubiDaten', null)
const istAusbilder = loadState<boolean>('berichtsheft', 'istAusbilder', false)
const digestPraeferenzInitial = loadState<DigestPraeferenz | null>('berichtsheft', 'digestPraeferenz', null)

const vorname = ref(azubiDaten?.vorname ?? '')
const nachname = ref(azubiDaten?.nachname ?? '')
const angabenGespeichert = ref(false)

const WOCHENTAGE = [
	{ id: null, label: 'Standard (Montag)' },
	{ id: 1, label: 'Montag' },
	{ id: 2, label: 'Dienstag' },
	{ id: 3, label: 'Mittwoch' },
	{ id: 4, label: 'Donnerstag' },
	{ id: 5, label: 'Freitag' },
	{ id: 6, label: 'Samstag' },
	{ id: 7, label: 'Sonntag' },
]
const digestWochentag = ref<number | null>(digestPraeferenzInitial?.wochentag ?? null)
const digestStunde = ref<number | null>(digestPraeferenzInitial?.uhrzeitStunde ?? null)
const digestGespeichert = ref(false)

async function speicherePersoenlicheAngaben() {
	await api.put('/persoenliche-angaben', { vorname: vorname.value, nachname: nachname.value })
	angabenGespeichert.value = true
	setTimeout(() => { angabenGespeichert.value = false }, 3000)
}

async function speichereDigestPraeferenz() {
	await api.put('/digest-praeferenz', {
		wochentag: digestWochentag.value,
		uhrzeitStunde: digestStunde.value,
	})
	digestGespeichert.value = true
	setTimeout(() => { digestGespeichert.value = false }, 3000)
}
</script>

<template>
	<div class="berichtsheft-personal">
		<template v-if="azubiDaten !== null">
			<h2>Persönliche Angaben (Berichtsheft)</h2>
			<p class="hinweis">
				Erscheint auf dem Deckblatt deines Berichtshefts.
			</p>
			<div class="formular">
				<NcTextField v-model="vorname" label="Vorname" />
				<NcTextField v-model="nachname" label="Nachname" />
				<NcButton type="primary" @click="speicherePersoenlicheAngaben">Speichern</NcButton>
				<NcNoteCard v-if="angabenGespeichert" type="success">Gespeichert.</NcNoteCard>
			</div>
		</template>

		<template v-if="istAusbilder">
			<h2>Montags-Digest — eigener Zeitpunkt</h2>
			<p class="hinweis">
				Wählst du nichts aus, gilt Montag 10 Uhr als Standard (z.B.
				praktisch, wenn du montags Homeoffice hast und einen anderen
				Termin willst).
			</p>
			<div class="formular">
				<NcSelect
					:model-value="WOCHENTAGE.find(w => w.id === digestWochentag) ?? null"
					:options="WOCHENTAGE"
					label="label"
					input-label="Wochentag"
					@update:model-value="(w) => { digestWochentag = w ? w.id : null }" />
				<NcTextField
					:model-value="digestStunde?.toString() ?? ''"
					type="number"
					label="Uhrzeit-Stunde (0-23, leer = Standard 10 Uhr)"
					@update:model-value="(v) => { digestStunde = v === '' ? null : Number(v) }" />
				<NcButton type="primary" @click="speichereDigestPraeferenz">Speichern</NcButton>
				<NcNoteCard v-if="digestGespeichert" type="success">Gespeichert.</NcNoteCard>
			</div>
		</template>
	</div>
</template>

<style scoped>
.berichtsheft-personal {
	max-width: 500px;
	margin: 16px;
}
.formular {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-bottom: 24px;
}
.hinweis {
	color: var(--color-text-maxcontrast);
	margin-bottom: 8px;
}
</style>
