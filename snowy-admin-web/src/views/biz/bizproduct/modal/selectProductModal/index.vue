<template>
	<a-row>
		<a-card :bordered="false">
			<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
				<a-row :gutter="24">
					<a-col :span="6">
						<a-form-item label="产品名称" name="productName">
							<a-input
								:disabled="disableSearchFromKey.productName"
								v-model:value="searchFormState.productName"
								placeholder="请输入产品名称"
							/>
						</a-form-item>
					</a-col>
					<a-col :span="6">
						<a-form-item label="产品分类" name="productCategory">
							<a-select
								:disabled="disableSearchFromKey.productCategory"
								v-model:value="searchFormState.productCategory"
								placeholder="产品分类"
								:options="productCategoryOptions"
							/>
						</a-form-item>
					</a-col>
					<a-col :span="4">
						<a-form-item label="类别" name="category">
							<a-select
								:disabled="disableSearchFromKey.category"
								v-model:value="searchFormState.category"
								placeholder="选择类别"
								:options="categoryOptions"
							/>
						</a-form-item>
					</a-col>
					<a-col :span="8">
						<a-form-item label="过滤范围" name="onlyCurrentCompany">
							<a-checkbox v-model:checked="searchFormState.onlyCurrentCompany">仅查看本公司产品</a-checkbox>
						</a-form-item>
					</a-col>
					<a-col :span="12">
						<a-form-item label="创建时间" name="createTime">
							<a-range-picker
								value-format="YYYY-MM-DD HH:mm:ss"
								:disabled="disableSearchFromKey.createTime"
								v-model:value="searchFormState.createTime"
								show-time
							/>
						</a-form-item>
					</a-col>
					<a-col :span="6">
						<a-button type="primary" @click="tableRef.refresh()">查询</a-button>
						<a-button style="margin: 0 8px" @click="reset">重置</a-button>
					</a-col>
				</a-row>
			</a-form>
			<s-table
				:pageSizeOptions="['5', '10', '20', '50', '100']"
				:defaultPageSize="5"
				ref="tableRef"
				:columns="columns"
				:data="loadData"
				:alert="options.alert.show"
				bordered
				:row-key="(record) => record.id"
				:tool-config="toolConfig"
				:row-selection="options.rowSelection"
			>
				<template #operator class="table-operator">
					<a-space>
						<a-button
							type="primary"
							@click="
								formRef.onOpen({
									category: 'SINGLE_PRODUCT'
								})
							"
							v-if="hasPerm('bizProductAdd')"
						>
							<template #icon>
								<plus-outlined />
							</template>
							新增单品产品
						</a-button>

						<a-button
							type="primary"
							@click="
								formRef.onOpen({
									category: 'KIT_PRODUCT'
								})
							"
							v-if="hasPerm('bizKitProductAdd')"
						>
							<template #icon>
								<plus-outlined />
							</template>
							新增套件产品
						</a-button>
						<!--					<xn-batch-delete-->
						<!--						v-if="hasPerm('bizProductBatchDelete')"-->
						<!--						:selectedRowKeys="selectedRowKeys"-->
						<!--						@batchDelete="deleteBatchBizProduct"-->
						<!--					/>-->
					</a-space>
				</template>
				<template #bodyCell="{ column, record }">
					<template v-if="column.dataIndex === 'productCategory'">
						{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.productCategory) }}
					</template>
					<template v-if="column.dataIndex === 'category'">
						<a-tag :color="$TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE_COLOR', record.category)">
							{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE', record.category) }}
						</a-tag>
					</template>
				</template>
			</s-table>
		</a-card>
	</a-row>
	<Form ref="formRef" @successful="tableRef.refresh()" />
</template>

<script setup name="selectProductModal">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import bizProductApi from '@/api/biz/bizProductApi'
	import Form from '@/views/biz/bizproduct/form.vue'
	import { useOrg } from '@/composables/useOrg'

	const props = defineProps({
		ignoreIdList: {
			type: Array, // 使用 Array 而不是 []
			default: () => [] // 默认值为一个空数组
		},
		defaultSearchFrom: {
			type: Object,
			default: () => {
				return {}
			}
		},
		disableSearchFromKey: {
			type: Object,
			default: () => {
				return {
					productName: false,
					productCategory: false,
					category: false,
					createTime: false
				}
			}
		},
		modelValue: {
			type: Array,
			default: () => []
		}
	})

	//定义v-model
	const selectedRowKeys = ref()
	const model = defineModel()

	//需要忽略的id列表
	const searchFormState = ref(
		Object.assign(
			{
				onlyCurrentCompany: true
			},
			props.defaultSearchFrom
		)
	)
	const searchFormRef = ref()

	const tableRef = ref()
	const formRef = ref()
	const { loadingTreeData, getCurrentUserTopOrgId } = useOrg()
	const currentUserTopOrgId = ref(null)
	const initCurrentUserTopOrgId = async () => {
		await loadingTreeData()
		currentUserTopOrgId.value = getCurrentUserTopOrgId()
		return currentUserTopOrgId.value
	}
	const currentUserTopOrgIdReady = initCurrentUserTopOrgId()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	// 查询区域显示更多控制
	const advanced = ref(false)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}
	const columns = [
		{
			title: '产品名称',
			dataIndex: 'productName'
		},
		{
			title: '产品分类',
			dataIndex: 'productCategory'
		},
		// {
		// 	title: '安全库存',
		// 	dataIndex: 'safetyStock'
		// },
		// {
		// 	title: '采购价格',
		// 	dataIndex: 'purchasePrice'
		// },
		{
			title: '销售价格',
			dataIndex: 'salePrice'
		},
		// {
		// 	title: '最低售价',
		// 	dataIndex: 'minPrice'
		// },
		{
			title: '类别',
			dataIndex: 'category'
		},
		{
			title: '创建时间',
			dataIndex: 'createTime'
		},
		{
			title: '创建用户',
			dataIndex: 'createUserName'
		}
	]
	// 操作栏通过权限判断是否显示

	// 列表选择配置
	const options = {
		// columns数字类型字段加入 needTotal: true 可以勾选自动算账
		alert: {
			show: true,
			clear: () => {
				selectedRowKeys.value = ref([])
				model.value = ref([])
			}
		},
		rowSelection: {
			preserveSelectedRowKeys: true,
			onChange: (selectedRowKey, selectedRows) => {
				selectedRowKeys.value = selectedRowKey
				model.value = selectedRows
			}
		}
	}
	const loadData = async (parameter) => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// createTime范围查询条件重载
		if (searchFormParam.createTime) {
			searchFormParam.startCreateTime = searchFormParam.createTime[0]
			searchFormParam.endCreateTime = searchFormParam.createTime[1]
			delete searchFormParam.createTime
		}
		await currentUserTopOrgIdReady
		const requestParam = Object.assign(parameter, searchFormParam, {
			ignoreIdList: props.ignoreIdList.join(',')
		})
		if (requestParam.onlyCurrentCompany && currentUserTopOrgId.value) {
			requestParam.orgId = currentUserTopOrgId.value
		}
		delete requestParam.onlyCurrentCompany

		return bizProductApi
			.bizProductPage(requestParam)
			.then((data) => {
				return data
			})
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		searchFormState.value.onlyCurrentCompany = true

		tableRef.value.refresh(true)
	}

	const productCategoryOptions = tool.dictListByPath(['PRODUCT_DICT', 'PRODUCT_TYPE'])
	const categoryOptions = tool.dictListByPath(['PRODUCT_DICT', 'PRODUCT_SYS_TYPE'])
</script>

<style scoped></style>
