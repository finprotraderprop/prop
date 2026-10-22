<script setup>
import { ref, onMounted } from 'vue'

const health = ref('checking...')
onMounted(async () => {
  try {
    const r = await fetch('/api/health')
    health.value = (await r.json()).ok ? 'API reachable' : 'API error'
  } catch {
    health.value = 'API unreachable'
  }
})
</script>

<template>
  <el-container style="min-height: 100vh">
    <el-header><h2>CRM Admin</h2></el-header>
    <el-main>
      <el-alert :title="health" type="info" :closable="false" />
      <p>Next: login, clients, trading accounts, account groups, roles.</p>
    </el-main>
  </el-container>
</template>
