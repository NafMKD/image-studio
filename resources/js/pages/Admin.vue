<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeft, ShieldCheck } from '@lucide/vue'
import { Button } from '@/components/ui/button'

defineProps<{
  rows: { day: string; user: string; jobs: number; provider: string; charged: string; profit: string }[]
  users: number; generations: number; pending: number
  review: { id: number; client_request_id: string; email: string; created_at: string }[]
}>()
</script>

<template>
  <Head title="Super admin" />
  <main class="mx-auto min-h-screen max-w-6xl px-5 py-10 text-zinc-900 sm:px-8">
    <Link href="/studio" class="inline-flex items-center gap-2 text-sm text-zinc-500 hover:text-zinc-900"><ArrowLeft :size="16" /> Back to studio</Link>
    <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
      <div class="flex items-center gap-3"><ShieldCheck :size="28" /><div><p class="text-xs font-semibold uppercase tracking-widest text-zinc-400">Restricted</p><h1 class="text-3xl font-semibold tracking-tight">Super-admin report</h1></div></div>
      <Button variant="outline" as-child><a href="/admin/usage" target="_blank" rel="noopener noreferrer">Raw Pixelforge usage</a></Button>
    </div>
    <div class="mt-8 grid gap-4 sm:grid-cols-3">
      <div v-for="stat in [{ label: 'Users', value: users }, { label: 'All generations', value: generations }, { label: 'Active or needs review', value: pending }]" :key="stat.label" class="rounded-2xl border border-zinc-200 p-5">
        <p class="text-xs text-zinc-500">{{ stat.label }}</p><p class="mt-2 text-3xl font-semibold tabular-nums">{{ stat.value }}</p>
      </div>
    </div>
    <h2 class="mb-4 mt-10 text-lg font-semibold">Daily unit economics · UTC</h2>
    <div class="overflow-x-auto rounded-2xl border border-zinc-200">
      <table class="w-full min-w-[720px] text-left text-sm"><thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500"><tr>
        <th class="p-4">Day</th><th class="p-4">User</th><th class="p-4 text-right">Jobs</th><th class="p-4 text-right">Provider cost</th><th class="p-4 text-right">User charged</th><th class="p-4 text-right">Profit</th>
      </tr></thead><tbody><tr v-for="(row, i) in rows" :key="`${row.day}-${row.user}-${i}`" class="border-t border-zinc-100">
        <td class="p-4 tabular-nums">{{ row.day }}</td><td class="p-4">{{ row.user }}</td><td class="p-4 text-right tabular-nums">{{ row.jobs }}</td><td class="p-4 text-right tabular-nums">{{ row.provider }}</td><td class="p-4 text-right tabular-nums">{{ row.charged }}</td><td class="p-4 text-right font-semibold tabular-nums">{{ row.profit }}</td>
      </tr><tr v-if="rows.length === 0"><td colspan="6" class="p-8 text-center text-zinc-400">No completed generations.</td></tr></tbody></table>
    </div>
    <section v-if="review.length" class="mt-10 rounded-2xl border border-amber-200 bg-amber-50/60 p-5">
      <h2 class="font-semibold">Submissions requiring manual review</h2>
      <p class="mt-1 text-xs text-zinc-600">An HTTP timeout may have occurred after provider acceptance. Do not resubmit or release these holds until the provider dashboard confirms what happened.</p>
      <div v-for="item in review" :key="item.id" class="mt-3 border-t border-amber-200 pt-3 text-xs text-zinc-700">
        #{{ item.id }} · {{ item.email }} · {{ item.created_at }} · Request {{ item.client_request_id }}
      </div>
    </section>
    <p class="mt-4 max-w-3xl text-xs leading-5 text-zinc-500">Each completed generation is charged once. Failed jobs contribute zero. The provider usage link displays the raw /v1/usage response; its reconciliation fields must be mapped after checking the official API contract.</p>
  </main>
</template>
