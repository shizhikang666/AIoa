<template>
	<xn-form-container title="添加收款记录" :width="550" :visible="visible" :destroy-on-close="true" @close="onClose">
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<!--			<a-form-item label="打款人：" name="payer">-->
			<!--				<a-input placeholder="请输入打款人" v-model:value="formData.payer"></a-input>-->
			<!--			</a-form-item>-->
			<a-form-item label="收款账户：" name="accountId">
				<a-select placeholder="请选择收款账户" v-model:value="formData.accountId" :options="accountList"></a-select>
			</a-form-item>
			<a-form-item label="收款金额：" name="amount">
				<XnCurrencyInput :min="0" v-model:value="formData.amount" placeholder="请输入收款金额" />
			</a-form-item>

			<a-form-item label="备注：" name="remark">
				<a-textarea v-model:value="formData.remark" placeholder="请输入备注" :auto-size="{ minRows: 5, maxRows: 5 }" />
			</a-form-item>
			<a-form-item label="收款日期：" name="payerTime">
				<a-date-picker v-model:value="formData.payerTime" value-format="YYYY-MM-DD HH:mm:ss" show-time></a-date-picker>
			</a-form-item>

			<a-form-item label="财务：" name="treasurer">
				<xn-user-selector
					:dataIsConverterFlw="false"
					:radioModel="true"
					:org-tree-api="selectorApiFunction.orgTreeApi"
					:user-page-api="selectorApiFunction.userPageApi"
					:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
					v-model:value="formData.treasurer"
				/>
			</a-form-item>
			<!--			<a-form-item label="审批人：" name="approveUserIdList">-->
			<!--				<xn-user-selector-->
			<!--					:org-tree-api="selectorApiFunction.orgTreeApi"-->
			<!--					:user-page-api="selectorApiFunction.userPageApi"-->
			<!--					:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"-->
			<!--					data-type="object"-->
			<!--					v-model:value="formData.approveUserIdList"-->
			<!--				/>-->
			<!--			</a-form-item>-->
			<!--			<a-form-item label="抄送人：" name="copyUserIdList">-->
			<!--				<xn-user-selector-->
			<!--					:org-tree-api="selectorApiFunction.orgTreeApi"-->
			<!--					:user-page-api="selectorApiFunction.userPageApi"-->
			<!--					:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"-->
			<!--					data-type="object"-->
			<!--					v-model:value="formData.copyUserIdList"-->
			<!--				/>-->
			<!--			</a-form-item>-->
		</a-form>
		<template #footer>
			<a-button class="xn-mr8" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="sendLoading">发送</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="startPlayFlowForm">
	import { required } from '@/utils/formRules'
	import { message } from 'ant-design-vue'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import userApi from '@/api/sys/userApi'
	import userCenterApi from '@/api/sys/userCenterApi'
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import SettlementAccountApi from '@/api/biz/settlementAccountApi'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import { useUserSelector } from '@/composables/useUserSelector'
	import { useProcessParam } from '@/composables/useProcessParam'
	import dayjs from 'dayjs'
	import { computed } from 'vue'
	import { Decimal } from 'decimal.js'

	const sendLoading = ref(false)
	// 定义emit事件
	const emit = defineEmits({ successful: null })
	// 默认是关闭状态
	const visible = ref(false)
	const formRef = ref()
	// 表单数据
	const formData = ref({})

	const accountList = ref([])

	// 关闭抽屉

	// 格式化当前时间
	const formattedDate = dayjs().format('YYYY-MM-DD HH:mm:ss')
	// 打开抽屉
	const onOpen = (record) => {
		visible.value = true
		const { copyUserIdList, approveUserIdList, treasurer } = useProcessParam('Process_sale_project_play')
		let result = new Decimal(record.totalPrice).sub(new Decimal(record.amountCollected)).toNumber()
		if (record.auditAmount) {
			result = new Decimal(record.totalPrice)
				.sub(new Decimal(record.amountCollected))
				.sub(new Decimal(record.auditAmount))
				.toNumber()
		}

		result = result > 0 ? result : 0
		formData.value = {
			projectId: record.id,
			accountId: record.accountId,
			payerTime: formattedDate,
			approveUserIdList: approveUserIdList,
			copyUserIdList: copyUserIdList,
			treasurer: treasurer,
			amount: result,
			remark: `项目：${record.projectName}`
		}

		SettlementAccountApi.settlementAccountList().then((res) => {
			accountList.value = res.map((v) => {
				return {
					label: v.accountName,
					value: v.id
				}
			})
		})
	}
	// 关闭抽屉
	const onClose = () => {
		emit('successful')
		visible.value = false
	}
	// 默认要校验的
	const formRules = {
		treasurer: [required('请选择财务')],
		payerTime: [required('付款时间')],
		amount: [required('收款金额不能为空')],
		accountId: [required('收款账户为空')],
		remark: [required('备注不能为空')]
	}
	// 站内信分类字典

	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				sendLoading.value = true
				let form = cloneDeep(formData.value)
				bizProcessApi
					.bizProcessStartProjectPlay(form)
					.then((res) => {
						onClose()
					})
					.finally(() => {
						sendLoading.value = false
					})

				// bizSaleProjectApi.bizSaleProjectApplyApproval(form)
				// 	.then(() => {
				// 		onClose()
				// 	})
				// 	.finally(() => {
				// 		sendLoading.value = false
				// 	})
			})
			.catch(() => {})
	}
	// 传递设计器需要的API
	const selectorApiFunction = useUserSelector()

	// 调用这个函数将子组件的一些数据和方法暴露出去
	defineExpose({
		onOpen
	})
</script>
