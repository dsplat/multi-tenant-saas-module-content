<template>
  <div class="page-container">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>话术库</span>
          <el-button type="primary" @click="handleCreate"> 新建话术 </el-button>
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

    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="680px"
      :close-on-click-modal="false"
      @close="formRef?.resetFields()"
    >
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="80px">
        <el-form-item label="标题" prop="title">
          <el-input v-model="formData.title" placeholder="请输入话术标题" maxlength="100" />
        </el-form-item>
        <el-form-item label="分类" prop="category">
          <el-input v-model="formData.category" placeholder="请输入分类" maxlength="50" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-select v-model="formData.status" placeholder="选择状态">
            <el-option label="草稿" value="draft" />
            <el-option label="已启用" value="approved" />
            <el-option label="已归档" value="archived" />
          </el-select>
        </el-form-item>
        <el-form-item label="内容" prop="content">
          <el-input
            v-model="formData.content"
            type="textarea"
            placeholder="请输入话术内容"
            :rows="6"
            maxlength="2000"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="标签">
          <el-input v-model="formData.tagsText" placeholder="多个标签用逗号分隔" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false"> 取消 </el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit"> 确定 </el-button>
      </template>
    </el-dialog>

    <el-drawer v-model="detailVisible" title="话术详情" size="500px">
      <template v-if="detailData">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="标题">
            {{ detailData.title }}
          </el-descriptions-item>
          <el-descriptions-item label="分类">
            {{ detailData.category }}
          </el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="statusTagType(detailData.status)" size="small">
              {{ statusLabel(detailData.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="标签">
            <el-tag v-for="tag in detailData.tags" :key="tag" style="margin-right: 4px">
              {{ tag }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="使用次数">
            {{ detailData.usage_count ?? 0 }}
          </el-descriptions-item>
          <el-descriptions-item label="内容">
            <div style="white-space: pre-wrap">
              {{ detailData.content }}
            </div>
          </el-descriptions-item>
          <el-descriptions-item label="创建时间">
            {{ detailData.created_at }}
          </el-descriptions-item>
        </el-descriptions>
      </template>
    </el-drawer>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, h } from 'vue'
import { ElMessage, ElMessageBox, ElTag, type FormInstance, type FormRules } from 'element-plus'
import ProTable from '@multi-tenant-saas/console/components/common/ProTable/ProTable.vue'
import type {
  ColumnConfig,
  SearchConfig,
  ActionConfig,
  RequestParams,
  RequestResult,
} from '@multi-tenant-saas/console/components/common/ProTable/ProTable.vue'
import {
  getScriptList,
  createScript,
  updateScript,
  deleteScript,
  getScriptCategories,
  recordScriptUsage,
  duplicateScript,
  type Script,
  type ScriptListParams,
} from '@modules/Content/api/script'

defineOptions({ name: 'ScriptLibrary' })

const tableRef = ref<InstanceType<typeof ProTable>>()
const dialogVisible = ref(false)
const dialogTitle = ref('新建话术')
const submitting = ref(false)
const formRef = ref<FormInstance>()
const detailVisible = ref(false)
const detailData = ref<Script | null>(null)
const editingId = ref<number | null>(null)

const defaultFormData = {
  title: '',
  category: '',
  content: '',
  tagsText: '',
  status: 'draft',
}

const formData = reactive({ ...defaultFormData })

const formRules: FormRules = {
  title: [{ required: true, message: '请输入标题', trigger: 'blur' }],
  content: [{ required: true, message: '请输入话术内容', trigger: 'blur' }],
}

// --- 分类选项（从 API 动态加载）---
const categoryOptions = ref<{ label: string; value: string }[]>([])

onMounted(async () => {
  try {
    const cats = await getScriptCategories()
    categoryOptions.value = cats.map((c) => ({ label: `${c.category} (${c.count})`, value: c.category }))
  } catch {
    // 静默失败，筛选器降级为空
  }
})

const searchConfig: SearchConfig[] = [
  { prop: 'keyword', label: '关键词', type: 'input', placeholder: '搜索标题/内容' },
  {
    prop: 'category',
    label: '分类',
    type: 'select',
    placeholder: '全部分类',
    options: [], // 动态填充
  },
  {
    prop: 'status',
    label: '状态',
    type: 'select',
    placeholder: '全部状态',
    options: [
      { label: '草稿', value: 'draft' },
      { label: '已启用', value: 'approved' },
      { label: '已归档', value: 'archived' },
    ],
  },
]

// 延迟一帧把分类 options 塞进 searchConfig（等 API 返回）
const _catWatchTimer = setInterval(() => {
  if (categoryOptions.value.length > 0) {
    const catCfg = searchConfig.find((c) => c.prop === 'category')
    if (catCfg && (!catCfg.options || catCfg.options.length === 0)) {
      catCfg.options = [...categoryOptions.value]
    }
    clearInterval(_catWatchTimer)
  }
}, 500)

// --- 状态标签 ---
function statusTagType(s: string) {
  return s === 'approved' ? 'success' : s === 'archived' ? 'info' : 'warning'
}
function statusLabel(s: string) {
  return s === 'approved' ? '已启用' : s === 'archived' ? '已归档' : '草稿'
}

const columns: ColumnConfig[] = [
  { prop: 'id', label: 'ID', width: 80 },
  { prop: 'title', label: '标题', minWidth: 180 },
  { prop: 'category', label: '分类', width: 120 },
  {
    prop: 'status',
    label: '状态',
    width: 90,
    render: (row: any) =>
      h(ElTag, { type: statusTagType(row.status), size: 'small' }, () => statusLabel(row.status)),
  },
  { prop: 'usage_count', label: '使用次数', width: 100 },
  { prop: 'createdAt', label: '创建时间', width: 180, sortable: true },
]

const actions: ActionConfig[] = [
  {
    label: '复制使用',
    type: 'success',
    onClick: (row: any) => handleCopyUse(row as Script),
  },
  { label: '复制', type: 'primary', onClick: (row: any) => handleDuplicate(row as Script) },
  { label: '详情', type: 'primary', onClick: (row) => handleDetail(row as Script) },
  { label: '编辑', type: 'primary', onClick: (row) => handleEdit(row as Script) },
  { label: '删除', type: 'danger', onClick: (row) => handleDelete(row as Script) },
]

async function handleRequest(params: RequestParams): Promise<RequestResult> {
  try {
    const query: ScriptListParams = { page: params.page, pageSize: params.pageSize }
    if (params.keyword) query.keyword = params.keyword
    if (params.category) query.category = params.category
    if (params.status) query.status = params.status
    const res = await getScriptList(query)
    return { data: res.data ?? [], total: res.total ?? 0 }
  } catch (e: any) {
    ElMessage.error(e.message || '获取话术列表失败')
    return { data: [], total: 0 }
  }
}

function handleCreate() {
  dialogTitle.value = '新建话术'
  editingId.value = null
  Object.assign(formData, defaultFormData)
  dialogVisible.value = true
}

function handleEdit(row: Script) {
  dialogTitle.value = '编辑话术'
  editingId.value = row.script_id
  formData.title = row.title
  formData.category = row.category || ''
  formData.content = row.content
  formData.tagsText = (row.tags ?? []).join(', ')
  formData.status = row.status || 'draft'
  dialogVisible.value = true
}

function handleDetail(row: Script) {
  detailData.value = row
  detailVisible.value = true
}

/** 复制话术为新草稿副本（内容照抄，status 重置草稿） */
async function handleDuplicate(row: Script) {
  try {
    await duplicateScript(row.script_id)
    ElMessage.success('已复制为草稿副本')
    tableRef.value?.refresh()
  } catch (e: any) {
    ElMessage.error(e.message || '复制失败')
  }
}

/** 复制话术内容到剪贴板 + 递增使用次数 */
async function handleCopyUse(row: Script) {
  try {
    await navigator.clipboard.writeText(row.content)
    ElMessage.success('话术内容已复制到剪贴板')
    // 异步记录使用次数，不阻塞用户
    recordScriptUsage(row.script_id).catch(() => {})
    tableRef.value?.refresh()
  } catch {
    ElMessage.error('复制失败，请手动复制')
  }
}

async function handleSubmit() {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
  } catch {
    return
  }

  const payload = {
    title: formData.title,
    category: formData.category,
    content: formData.content,
    status: formData.status,
    tags: formData.tagsText
      ? formData.tagsText
          .split(',')
          .map((t) => t.trim())
          .filter(Boolean)
      : [],
  }

  submitting.value = true
  try {
    if (editingId.value !== null) {
      await updateScript(editingId.value, payload)
    } else {
      await createScript(payload)
    }
    ElMessage.success('操作成功')
    dialogVisible.value = false
    tableRef.value?.refresh()
  } catch (e: any) {
    ElMessage.error(e.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

async function handleDelete(row: Script) {
  try {
    await ElMessageBox.confirm('确定删除该话术吗？', '提示', { type: 'warning' })
    await deleteScript(row.script_id)
    ElMessage.success('删除成功')
    tableRef.value?.refresh()
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '删除失败')
    }
  }
}
</script>

<style scoped>
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
