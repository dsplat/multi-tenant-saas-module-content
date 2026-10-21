<template>
  <div class="page-container">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>素材管理</span>
          <el-button type="primary" @click="handleCreate"> 新建 </el-button>
        </div>
      </template>

      <ProTable
        ref="tableRef"
        :columns="columns"
        :request="handleRequest"
        :search-config="searchConfig"
        :actions="actions"
      />
    </el-card>

    <!-- 新建/编辑弹窗 -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑素材' : '新建素材'" width="600px">
      <el-form :model="form" label-width="80px">
        <el-form-item label="素材名称">
          <el-input v-model="form.title" placeholder="请输入素材名称" />
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="form.type" placeholder="选择类型">
            <el-option label="文档" value="article" />
            <el-option label="图片" value="image" />
            <el-option label="文本" value="text" />
            <el-option label="视频" value="video" />
            <el-option label="文件" value="file" />
          </el-select>
        </el-form-item>
        <el-form-item label="业务分类">
          <el-input v-model="form.category" placeholder="如 campaign-material" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio value="active"> 启用 </el-radio>
            <el-radio value="inactive"> 停用 </el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false"> 取消 </el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit"> 确定 </el-button>
      </template>
    </el-dialog>

    <!-- 查看详情抽屉 -->
    <el-drawer v-model="detailVisible" title="素材详情" size="560px">
      <template v-if="detailData">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="标题">{{ detailData.title }}</el-descriptions-item>
          <el-descriptions-item label="类型">
            <el-tag size="small">{{ typeLabel(detailData.type) }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="业务分类">
            {{ categoryLabel(detailData.category) }}
          </el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="detailData.status === 'active' ? 'success' : 'info'" size="small">
              {{ detailData.status === 'active' ? '启用' : '停用' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ detailData.created_at }}</el-descriptions-item>
          <el-descriptions-item label="内容">
            <div
              v-if="detailData.content"
              class="markdown-body"
              v-html="renderMarkdown(detailData.content)"
            />
            <span v-else>（无文本内容）</span>
          </el-descriptions-item>
        </el-descriptions>
      </template>
    </el-drawer>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, h } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox, ElTag, ElButton } from 'element-plus'
import { marked } from 'marked'
import ProTable from '@multi-tenant-saas/console/components/common/ProTable/ProTable.vue'
import type {
  ColumnConfig,
  SearchConfig,
  ActionConfig,
  RequestParams,
  RequestResult,
} from '@multi-tenant-saas/console/components/common/ProTable/ProTable.vue'
import {
  getMaterialList,
  createMaterial,
  updateMaterial,
  deleteMaterial,
  duplicateMaterial,
  type Material,
  type MaterialListParams,
} from '@modules/Content/api/material'

defineOptions({ name: 'MaterialList' })

const router = useRouter()
const tableRef = ref<InstanceType<typeof ProTable>>()
const dialogVisible = ref(false)
const isEdit = ref(false)
const editId = ref<number | null>(null)
const submitting = ref(false)
const detailVisible = ref(false)
const detailData = ref<Material | null>(null)

const form = reactive({ title: '', type: 'article', category: '', status: 'active' })

// ---- 标签映射 ----

function typeLabel(t: string) {
  const map: Record<string, string> = { article: '文档', image: '图片', text: '文本', video: '视频', file: '文件' }
  return map[t] ?? t
}

/** 业务分类：slug → 中文名 */
function categoryLabel(slug: string): string {
  const map: Record<string, string> = {
    'campaign-material': '活动素材',
    'course-material': '课程素材',
    'product-material': '商品素材',
    'poster-material': '海报素材',
    'general': '通用素材',
  }
  return map[slug] ?? slug ?? '-'
}

/** entity_type → 前往应用的路由 + 标签 */
function entityRoute(entityType: string): { path: string; label: string } | null {
  const map: Record<string, { path: string; label: string }> = {
    activity_plan: { path: '/activity', label: '查看活动' },
    activity:      { path: '/activity', label: '查看活动' },
    course:        { path: '/courses', label: '查看课程' },
    product:       { path: '/products', label: '查看商品' },
    poster:        { path: '/posters', label: '查看海报' },
    lottery:       { path: '/lotteries', label: '查看抽奖' },
    coupon:        { path: '/coupons', label: '查看优惠券' },
    survey:        { path: '/surveys', label: '查看问卷' },
    vote:          { path: '/votings', label: '查看投票' },
  }
  return map[entityType] ?? null
}

// ---- Markdown 渲染 ----

function renderMarkdown(md: string): string {
  return marked.parse(md, { async: false }) as string
}

// ---- ProTable 配置 ----

const searchConfig: SearchConfig[] = [
  { prop: 'search', label: '关键词', type: 'input', placeholder: '搜索标题/内容' },
  {
    prop: 'type',
    label: '类型',
    type: 'select',
    placeholder: '全部类型',
    options: [
      { label: '文档', value: 'article' },
      { label: '图片', value: 'image' },
      { label: '文本', value: 'text' },
      { label: '视频', value: 'video' },
      { label: '文件', value: 'file' },
    ],
  },
  {
    prop: 'status',
    label: '状态',
    type: 'select',
    placeholder: '全部状态',
    options: [
      { label: '启用', value: 'active' },
      { label: '停用', value: 'inactive' },
    ],
  },
]

const columns: ColumnConfig[] = [
  { prop: 'material_id', label: 'ID', width: 80 },
  { prop: 'title', label: '名称', minWidth: 180 },
  {
    prop: 'type',
    label: '类型',
    width: 90,
    render: (row: any) => h(ElTag, { size: 'small' }, () => typeLabel(row.type)),
  },
  {
    prop: 'category',
    label: '业务分类',
    width: 150,
    render: (row: any) => categoryLabel(row.category),
  },
  {
    prop: 'status',
    label: '状态',
    width: 90,
    render: (row: any) =>
      h(
        ElTag,
        { type: row.status === 'active' ? 'success' : 'info', size: 'small' },
        () => (row.status === 'active' ? '启用' : '停用'),
      ),
  },
  { prop: 'created_at', label: '创建时间', width: 180, sortable: true },
]

const actions: ActionConfig[] = [
  { label: '查看', type: 'primary', onClick: (row) => handleDetail(row as Material) },
  {
    label: '前往',
    type: 'success',
    visible: (row) => !!(row as Material).entity_type && entityRoute((row as Material).entity_type!),
    onClick: (row) => {
      const r = entityRoute((row as Material).entity_type!)
      if (r) router.push(r.path)
    },
  },
  { label: '复制', type: 'primary', onClick: (row) => handleDuplicate(row as Material) },
  { label: '编辑', type: 'primary', onClick: (row) => handleEdit(row as Material) },
  { label: '删除', type: 'danger', onClick: (row) => handleDelete(row as Material) },
]

// ---- 请求 & CRUD ----

async function handleRequest(params: RequestParams): Promise<RequestResult> {
  try {
    const query: MaterialListParams = { page: params.page, pageSize: params.pageSize }
    if (params.search) query.search = params.search
    if (params.type) query.type = params.type
    if (params.status) query.status = params.status
    const res = await getMaterialList(query)
    return { data: res.data ?? [], total: res.total ?? 0 }
  } catch (e: any) {
    ElMessage.error(e.message || '获取素材列表失败')
    return { data: [], total: 0 }
  }
}

function handleCreate() {
  isEdit.value = false
  editId.value = null
  Object.assign(form, { title: '', type: 'article', category: '', status: 'active' })
  dialogVisible.value = true
}

function handleEdit(row: Material) {
  isEdit.value = true
  editId.value = row.material_id
  Object.assign(form, {
    title: row.title,
    type: row.type ?? 'article',
    category: row.category ?? '',
    status: row.status ?? 'active',
  })
  dialogVisible.value = true
}

function handleDetail(row: Material) {
  detailData.value = row
  detailVisible.value = true
}

async function handleSubmit() {
  if (!form.title) {
    ElMessage.warning('请输入素材名称')
    return
  }
  submitting.value = true
  try {
    const payload = { title: form.title, type: form.type, category: form.category, status: form.status }
    if (isEdit.value && editId.value) {
      await updateMaterial(editId.value, payload)
      ElMessage.success('已更新')
    } else {
      await createMaterial(payload)
      ElMessage.success('已创建')
    }
    dialogVisible.value = false
    tableRef.value?.refresh()
  } catch {
    ElMessage.error('操作失败')
  } finally {
    submitting.value = false
  }
}

async function handleDuplicate(row: Material) {
  try {
    await duplicateMaterial(row.material_id)
    ElMessage.success('已复制为草稿副本')
    tableRef.value?.refresh()
  } catch (e: any) {
    ElMessage.error(e.message || '复制失败')
  }
}

async function handleDelete(row: Material) {
  await ElMessageBox.confirm(`确定删除素材「${row.title}」？`)
  await deleteMaterial(row.material_id)
  ElMessage.success('已删除')
  tableRef.value?.refresh()
}
</script>

<style scoped lang="scss">
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

/* Markdown 渲染区域样式 */
.markdown-body {
  max-height: 400px;
  overflow-y: auto;
  line-height: 1.7;
  font-size: 14px;

  :deep(h1), :deep(h2), :deep(h3) {
    margin: 0.8em 0 0.4em;
    font-weight: 600;
  }
  :deep(h1) { font-size: 1.4em; }
  :deep(h2) { font-size: 1.2em; }
  :deep(h3) { font-size: 1.1em; }
  :deep(p) { margin: 0.5em 0; }
  :deep(ul), :deep(ol) { padding-left: 1.5em; margin: 0.5em 0; }
  :deep(table) {
    border-collapse: collapse;
    width: 100%;
    margin: 0.5em 0;
    th, td {
      border: 1px solid var(--el-border-color-lighter, #ebeef5);
      padding: 6px 10px;
      text-align: left;
    }
    th { background: var(--el-fill-color-light, #f5f7fa); font-weight: 600; }
  }
  :deep(code) {
    background: var(--el-fill-color-light, #f5f7fa);
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 0.9em;
  }
  :deep(pre) {
    background: var(--el-fill-color-light, #f5f7fa);
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
    code { background: none; padding: 0; }
  }
  :deep(hr) {
    border: none;
    border-top: 1px solid var(--el-border-color-lighter, #ebeef5);
    margin: 1em 0;
  }
  :deep(input[type="checkbox"]) {
    margin-right: 4px;
  }
}
</style>
