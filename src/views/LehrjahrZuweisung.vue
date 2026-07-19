<script setup lang="ts">
import { onMounted, ref } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { api } from '../api.ts'

const props = defineProps<{ azubiId: number }>()
const emit = defineEmits<{ close: [] }>()

interface Zuweisung {
	id: number
	gueltigAb: string
	lehrjahr: number
	festgelegtVonName: string
	festgelegtAm: number
}

const historie = ref<Zuweisung[]>([])
const neuGueltigAb = ref(new Date().toISOString().slice(0, 10))
const neuLehrjahr = ref(1)

async function lade() {
	const { data } = await api.get<Zuweisung[]>(`/lehrjahr/${props.azubiId}`)
	historie.value = data
}

async function anlegen() {
	await api.post(`/lehrjahr/${props.azubiId}`, {
		gueltigAb: neuGueltigAb.value,
		lehrjahr: neuLehrjahr.value,
	})
	await lade()
}

onMounted(lade)
</script>

<template>
	<NcDialog name="Lehrjahr-Zuweisung" @closing="emit('close')">
		<p class="hinweis">
			Lehrjahr wird bewusst nicht berechnet, sondern hier festgelegt -
			ein wiederholender Azubi ist z.B. im 2. Ausbildungsjahr, aber
			weiterhin im 1. Lehrjahr.
		</p>
		<table class="historie-tabelle">
			<thead>
				<tr>
					<th>Gueltig ab</th>
					<th>Lehrjahr</th>
					<th>Festgelegt von</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="z in historie" :key="z.id">
					<td>{{ z.gueltigAb }}</td>
					<td>{{ z.lehrjahr }}</td>
					<td>{{ z.festgelegtVonName }}</td>
				</tr>
			</tbody>
		</table>

		<div class="neu-formular">
			<NcTextField :value.sync="neuGueltigAb" type="date" label="Gueltig ab" />
			<NcTextField :value.sync="neuLehrjahr" type="number" label="Lehrjahr" />
			<NcButton type="primary" @click="anlegen">Zuweisung anlegen</NcButton>
		</div>
	</NcDialog>
</template>

<style scoped>
.hinweis {
	color: var(--color-text-maxcontrast);
	margin-bottom: 12px;
}
.historie-tabelle {
	width: 100%;
	border-collapse: collapse;
	margin-bottom: 16px;
}
.historie-tabelle th, .historie-tabelle td {
	text-align: left;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
}
.neu-formular {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-width: 300px;
}
</style>
