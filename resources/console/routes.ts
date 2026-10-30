import type { RouteRecordRaw } from 'vue-router'
import { view } from '@multi-tenant-saas/console/module-loader'

const routes: RouteRecordRaw[] = [
  // 素材管理
  { path: 'materials', name: 'MaterialList', component: view('content', 'materials/MaterialList'), meta: { title: '素材管理', permission: 'material.view' } },
  // 话术库
  { path: 'scripts', name: 'ScriptLibrary', component: view('content', 'ScriptLibrary'), meta: { title: '话术库', permission: 'material.view' } },
]

export default routes
