<template>
	<xn-form-container
		:bodyStyle="{ paddingTop: 0 }"
		title="采购入库单"
		:width="800"
		:visible="visible"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-skeleton active :loading="loading">
			<a-tabs v-model:activeKey="activeKey">
				<a-tab-pane tab="基本信息" key="baseInfo">
					<a-form
						:labelCol="{ span: 3 }"
						labelAlign="left"
						ref="formRef"
						:model="formData"
						:rules="formRules"
						layout="horizontal"
					>
						<a-form-item label="采购单：" name="orderId">
							<a-typography-link :type="formData.objectId ? '' : 'danger'" @click="openProcureFlowSelect">
								{{
									activeSelectObject.id ? activeSelectObject.id + '(' + activeSelectObject.remark + ')' : '选择采购单'
								}}
							</a-typography-link>
						</a-form-item>

						<a-form-item label="物流编号：" name="logisticsId">
							<a-input placeholder="请输入物流编号" v-model:value="formData.logisticsId"></a-input>
						</a-form-item>

						<a-form-item label="仓库编号：" name="warehousesId">
							<a-row>
								<a-select
									show-search
									:filterOption="filterOption"
									placeholder="请选择入库仓库"
									v-model:value="formData.warehousesId"
									:options="warehousesList"
								></a-select>
							</a-row>
						</a-form-item>

						<a-form-item label="备注：" name="remark">
							<a-textarea
								v-model:value="formData.remark"
								placeholder="请输入备注"
								:auto-size="{ minRows: 5, maxRows: 5 }"
							/>
						</a-form-item>
						<!--					<a-form-item label="收款日期：" name="payerTime">-->
						<!--						<a-date-picker-->
						<!--							v-model:value="formData.payerTime"-->
						<!--							value-format="YYYY-MM-DD HH:mm:ss" show-time-->
						<!--						></a-date-picker>-->
						<!--					</a-form-item>-->
					</a-form>
				</a-tab-pane>
				<a-tab-pane tab="附件信息" key="file-list">
					<a-space>
						<a-button type="primary" @click="() => uploadFormRef.openUpload()">
							<UploadOutlined />
							文件上传
						</a-button>
					</a-space>

					<a-list item-layout="horizontal" :data-source="list">
						<template #renderItem="{ item, index }">
							<a-list-item key="item.id">
								<FileViewItem :item="item" @remove="list.splice(index, 1)"></FileViewItem>
							</a-list-item>
						</template>
					</a-list>
				</a-tab-pane>
				<a-tab-pane v-if="isOpenProcess" tab="审核信息" key="approve-info">
					<a-form ref="approveFormRef" :model="formData" :rules="formRules" layout="vertical">
						<a-form-item label="审批人：" name="approveUserIdList">
							<xn-user-selector
								:org-tree-api="selectorApiFunction.orgTreeApi"
								:user-page-api="selectorApiFunction.userPageApi"
								:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
								data-type="object"
								v-model:value="formData.approveUserIdList"
							/>
						</a-form-item>
						<a-form-item label="抄送人：" name="copyUserIdList">
							<xn-user-selector
								:org-tree-api="selectorApiFunction.orgTreeApi"
								:user-page-api="selectorApiFunction.userPageApi"
								:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
								data-type="object"
								v-model:value="formData.copyUserIdList"
							/>
						</a-form-item>
					</a-form>
				</a-tab-pane>
			</a-tabs>
		</a-skeleton>
		<template #footer>
			<a-button class="xn-mr8" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="sendLoading">发送</a-button>
		</template>
	</xn-form-container>
	<uploadForm ref="uploadFormRef" @successful="onUploadSuccess" />
</template>
<script setup name="startProcureInWarehouseFlowForm">
	import { useLoading } from '@/composables/useLoading'
	import SettlementAccountApi from '@/api/biz/settlementAccountApi'
	import { useUserSelector } from '@/composables/useUserSelector'
	import { required } from '@/utils/formRules'
	import { rules } from '@/utils/formRules'
	import tool from '@/utils/tool'
	import { useSelectFilterOption } from '@/composables/useSelectFilterOption'
	import { App } from 'ant-design-vue'
	import { createVNode, ref, useTemplateRef } from 'vue'
	import bizpurchaseorderModel from '@/views/biz/bizpurchaseorder/model/index.vue'
	import bizSaleProjectModal from '@/views/biz/saleproject/modal/index.vue'
	import bizProcessApi from '@/api/biz/bizProcessApi'

	import supplierApi from '@/api/biz/supplierApi'
	import UploadForm from '@/views/biz/file/uploadForm.vue'
	import dayjs from 'dayjs'
	import zhCn from 'dayjs/locale/zh-cn'
	import relativeTime from 'dayjs/plugin/relativeTime'
	import { openFilePreview } from '@/utils/filePreview'
	import { cloneDeep } from 'lodash-es'
	import { useProcessParam } from '@/composables/useProcessParam'
	import WarehousesApi from '@/api/biz/warehousesApi'
	import FileViewItem from '@/components/File/FileViewItem.vue'

	const { modal } = App.useApp()
	const visible = ref(false)
	const activeKey = ref('baseInfo')
	const formRef = useTemplateRef('formRef')
	const approveFormRef = useTemplateRef('approveFormRef')
	const uploadFormRef = useTemplateRef('uploadFormRef')
	dayjs.extend(relativeTime)
	// 设置中文显示
	dayjs.locale(zhCn)

	const formData = ref({})
	const filterOption = useSelectFilterOption()

	const selectorApiFunction = useUserSelector()

	const activeSelectObject = ref({})

	const openProcureFlowSelect = () => {
		let value = {}
		let content = createVNode(bizpurchaseorderModel, {
			defaultSearchFrom: {
				storageStatus: 'NOT_IN_WAREHOUSE'
			},
			disableSearchFromKey: {
				settlementStatus: true,
				storageStatus: true,
				supplierId: false,
				createTime: false
			},
			rowSelection: {
				type: 'radio',
				onSelect: (v) => {
					value = v
				},
				onChange: () => {}
			}
		})
		const onOk = () => {
			formData.value.orderId = value.id
			activeSelectObject.value = value
		}
		modal.confirm({
			icon: null,
			content: content,
			width: '1000px',
			onOk: onOk
		})
	}

	const { copyUserIdList, approveUserIdList, rule, isOpenProcess } = useProcessParam('Process_procure_in_warehouse')

	const formRules = {
		orderId: [required('请选择采购单号')],
		warehousesId: [rules.bankAccount, required('请选择仓库')],
		// logisticsId: [required('请输入物流单号')],
		...rule
	}
	const warehousesList = ref([])
	const list = ref([])
	const onClose = () => {
		list.value = []
		visible.value = false
	}

	const {
		load: onOpen,
		loading,
		error
	} = useLoading(async () => {
		const list = await WarehousesApi.warehousesList()
		warehousesList.value = list.map((v) => {
			return {
				label: v.name,
				value: v.id
			}
		})
		formData.value = {
			orderId: '',
			warehousesId: warehousesList.value.length ? warehousesList.value[0].value : '',
			approveUserIdList: approveUserIdList,
			copyUserIdList: copyUserIdList
		}
		visible.value = true
	})

	const onUploadSuccess = (res) => {
		list.value.push(res)
	}
	const { load: onSubmit, loading: sendLoading } = useLoading(async () => {
		try {
			await formRef.value.validate()
		} catch (err) {
			activeKey.value = 'baseInfo'
			return
		}
		if (isOpenProcess.value) {
			try {
				await approveFormRef.value.validate()
			} catch (err) {
				activeKey.value = 'approve-info'
				return
			}
		}
		const param = cloneDeep(formData.value)
		const fileIdList = list.value.map((v) => v.id)
		const form = {
			...param,
			fileIdList
		}

		await bizProcessApi.bizProcessStartProcureInWareHouse(form)
		activeSelectObject.value = {}
		formRef.value.resetFields()
		onClose()
	})

	defineExpose({
		onOpen
	})
</script>
<style scoped></style>
