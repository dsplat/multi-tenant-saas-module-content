import { http, extractListResult, moduleApiPrefix } from '@multi-tenant-saas/console/shared/http'

export interface Material {
  material_id: number
  title: string
  type: string
  content: string
  file_url?: string
  file_size?: number
  mime_type?: string
  category: string
  status: string
  entity_type?: string
  entity_id?: string
  created_at: string
  updated_at: string
}

export interface MaterialListParams {
  page: number
  pageSize: number
  type?: string
  category?: string
  status?: string
  search?: string
}

export interface MaterialListResult {
  data: Material[]
  total: number
}

export async function getMaterialList(params: MaterialListParams): Promise<MaterialListResult> {
  const { pageSize, ...filters } = params
  const res = await http.get<any>(`${moduleApiPrefix('content')}/materials`, {
    params: { ...filters, per_page: pageSize },
  })
  return extractListResult(res)
}

export async function createMaterial(data: Partial<Material>) {
  const res = await http.post(`${moduleApiPrefix('content')}/materials`, data)
  return res.data
}

export async function updateMaterial(id: number, data: Partial<Material>) {
  const res = await http.put(`${moduleApiPrefix('content')}/materials/${id}`, data)
  return res.data
}

export async function deleteMaterial(id: number) {
  const res = await http.delete(`${moduleApiPrefix('content')}/materials/${id}`)
  return res.data
}

export async function duplicateMaterial(id: number) {
  const res = await http.post(`${moduleApiPrefix('content')}/materials/${id}/duplicate`)
  return res.data
}
