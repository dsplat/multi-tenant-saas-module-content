import { http, extractListResult, moduleApiPrefix } from '@multi-tenant-saas/console/shared/http'

export interface Script {
  script_id: number
  title: string
  content: string
  category: string
  tags: string[]
  usage_count: number
  status: string
  entity_type?: string
  entity_id?: string
  created_at: string
  updated_at: string
}

export interface ScriptListParams {
  page: number
  pageSize: number
  keyword?: string
  category?: string
  status?: string
}

export interface ScriptListResult {
  data: Script[]
  total: number
}

export async function getScriptList(params: ScriptListParams): Promise<ScriptListResult> {
  const { pageSize, ...filters } = params
  const res = await http.get<any>(`${moduleApiPrefix('content')}/scripts`, {
    params: { ...filters, per_page: pageSize },
  })
  return extractListResult(res)
}

export async function createScript(data: Partial<Script>) {
  const res = await http.post(`${moduleApiPrefix('content')}/scripts`, data)
  return res.data
}

export async function updateScript(id: number, data: Partial<Script>) {
  const res = await http.put(`${moduleApiPrefix('content')}/scripts/${id}`, data)
  return res.data
}

export async function deleteScript(id: number) {
  const res = await http.delete(`${moduleApiPrefix('content')}/scripts/${id}`)
  return res.data
}

export async function getScriptCategories(): Promise<{ category: string; count: number }[]> {
  const res = await http.get<any>(`${moduleApiPrefix('content')}/scripts/categories`)
  return res.data?.data ?? res.data ?? []
}

export async function recordScriptUsage(id: number) {
  const res = await http.post(`${moduleApiPrefix('content')}/scripts/${id}/use`)
  return res.data
}

export async function duplicateScript(id: number) {
  const res = await http.post(`${moduleApiPrefix('content')}/scripts/${id}/duplicate`)
  return res.data
}
