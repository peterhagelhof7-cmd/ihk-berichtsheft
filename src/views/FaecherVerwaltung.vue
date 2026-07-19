<script setup lang="ts">
import { onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { api } from '../api.ts'

interface Fach {
	id: number
	name: string
	lehrjahre: number[]
}

// Bis zu 4 Lehrjahre (die AO2020-IT-Berufe sind ueberwiegend 3-jaehrig,
// verkuerzte/verlaengerte Ausbildungen kommen vor - daher nicht starr auf 3
// begrenzt).
const VERFUEGBARE_LEHRJAHRE = [1, 2, 3, 4]

const faecher = ref<Fach[]>([])
const neuName = ref('')
const neuLehrjahre = ref<number[]>([])

async function lade() {
	const { data } = await api.get<Fach[]>('/fach')
	faecher.value = data
}

async function anlegen() {
	if (!neuName.value.trim()) return
	await api.post('/fach', { name: neuName.value, lehrjahre: neuLehrjahre.value })
	neuName.value = ''
	neuLehrjahre.value = []
	await lade()
}

async function loeschen(id: number) {
	await api.delete(`/fach/${id}`)
	await lade()
}

async function lehrjahrUmschalten(fach: Fach, lehrjahr: number) {
	const neue = fach.lehrjahre.includes(lehrjahr)
		? fach.lehrjahre.filter((l) => l !== lehrjahr)
		: [...fach.lehrjahre, lehrjahr]
	await api.put(`/fach/${fach.id}`, { lehrjahre: neue })
	await lade()
}

onMounted(lade)
</script>

<template>
	<div class="faecher-verwaltung">
		<h2>Berufsschul-Faecher</h2>
		<p class="hinweis">
			Fuer die Berufsschul-Eingabe: manche Faecher gibt es nur im 1./2.
			Lehrjahr, manche nur im 3. - je Fach die geltenden Lehrjahre
			ankreuzen.
		</p>

		<table class="faecher-tabelle">
			<thead>
				<tr>
					<th>Fach</th>
					<th v-for="lj in VERFUEGBARE_LEHRJAHRE" :key="lj">LJ {{ lj }}</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="fach in faecher" :key="fach.id">
					<td>{{ fach.name }}</td>
					<td v-for="lj in VERFUEGBARE_LEHRJAHRE" :key="lj">
						<NcCheckboxRadioSwitch
							:model-value="fach.lehrjahre.includes(lj)"
							@update:model-value="lehrjahrUmschalten(fach, lj)" />
					</td>
					<td>
						<NcButton @click="loeschen(fach.id)">Loeschen</NcButton>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="neu-formular">
			<NcTextField v-model="neuName" label="Neues Fach" />
			<div class="lehrjahr-checkboxen">
				<NcCheckboxRadioSwitch
					v-for="lj in VERFUEGBARE_LEHRJAHRE"
					:key="lj"
					:model-value="neuLehrjahre.includes(lj)"
					@update:model-value="(v) => { neuLehrjahre = v ? [...neuLehrjahre, lj] : neuLehrjahre.filter(x => x !== lj) }">
					Lehrjahr {{ lj }}
				</NcCheckboxRadioSwitch>
			</div>
			<NcButton type="primary" @click="anlegen">Fach anlegen</NcButton>
		</div>
	</div>
</template>

<style scoped>
.faecher-verwaltung {
	margin-top: 24px;
}
.hinweis {
	color: var(--color-text-maxcontrast);
	margin-bottom: 8px;
}
.faecher-tabelle {
	width: 100%;
	border-collapse: collapse;
	margin-bottom: 16px;
}
.faecher-tabelle th, .faecher-tabelle td {
	text-align: left;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
}
.neu-formular {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-width: 400px;
}
.lehrjahr-checkboxen {
	display: flex;
	gap: 12px;
}
</style>
