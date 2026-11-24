<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ArrowLeft, Wallet } from '@lucide/vue'
import { Button } from '@/components/ui/button'

defineProps<{
  balance: string; available: string; spent: string; succeeded: number
  history: { id: number; type: string; amount: string; date: string }[]
}>()
</script>

<template>
  <Head title="Your dashboard" />
  <main class="mx-auto min-h-screen max-w-5xl px-5 py-10 text-zinc-900 sm:px-8">
    <Link href="/studio" class="inline-flex items-center gap-2 text-sm text-zinc-500 hover:text-zinc-900"><ArrowLeft :size="16" /> Back to studio</Link>
    <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
      <div><p class="text-xs font-semibold uppercase tracking-widest text-zinc-400">Account</p><h1 class="mt-1 text-3xl font-semibold tracking-tight">Your dashboard</h1></div>
      <Button @click="router.post('/credits')"><Wallet :size="16" class="mr-2" /> Buy $10 demo credits</Button>
    </div>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="stat in [{ label: 'Balance', value: balance }, { label: 'Available', value: available }, { label: 'Total spent', value: spent }, { label: 'Completed images', value: String(succeeded) }]" :key="stat.label" class="rounded-2xl border border-zinc-200 p-5 shadow-sm">
        <div class="text-xs text-zinc-500">{{ stat.label }}</div><div class="mt-2 text-2xl font-semibold tabular-nums">{{ stat.value }}</div>
      </div>
    </div>
    <section class="mt-10"><h2 class="mb-4 text-lg font-semibold">Credit activity</h2>
      <div class="overflow-hidden rounded-2xl border border-zinc-200">
        <div v-for="entry in history" :key="entry.id" class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 text-sm last:border-0">
          <div><div class="font-medium capitalize">{{ entry.type === 'topup' ? 'Demo credits added' : 'Image generation' }}</div><div class="mt-1 text-xs text-zinc-400">{{ entry.date }} UTC</div></div>
          <div class="font-semibold tabular-nums">{{ entry.amount }}</div>
        </div>
        <div v-if="!history.length" class="p-6 text-sm text-zinc-400">No credit activity yet.</div>
      </div>
    </section>
  </main>
</template>
