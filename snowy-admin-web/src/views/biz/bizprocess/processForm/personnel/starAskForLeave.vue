<template>
	<xn-form-container title="考勤申请" :width="550" :visible="visible" :destroy-on-close="true" @close="onClose">
		<a-skeleton active :loading="loading">
			<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
				<a-form-item label="请假类型：" name="category">
					<a-select placeholder="选择假期类型" v-model:value="formData.category" :options="categoryList"></a-select>
				</a-form-item>

				<a-form-item v-if="isBindProject(formData.category)" label="项目成交单：" name="objectId">
					<a-typography-link :type="formData.objectId ? '' : 'danger'" @click="openProjectSelect">
						{{
							activeSelectObject.id
								? activeSelectObject.id + '(' + activeSelectObject.projectName + ')'
								: '选择项目成交单'
						}}
					</a-typography-link>
				</a-form-item>
				<a-form-item :label="amountLabel" name="createTime">
					<a-range-picker
						:allowEmpty="allowEmpty"
						showToday
						:show-time="{
							defaultValue: [dayjs('08:00:00', 'HH:mm:ss'), dayjs('18:00:00', 'HH:mm:ss')],
							minuteStep: 60,
							hourStep: 2,
							secondStep: 60
						}"
						:disabled-time="disabledDateTime"
						value-format="YYYY-MM-DD HH:mm:ss"
						v-model:value="formData.createTime"
					/>
				</a-form-item>
				<a-form-item label="备注：" name="remark">
					<a-textarea
						v-model:value="formData.remark"
						placeholder="请输入备注"
						:auto-size="{ minRows: 5, maxRows: 5 }"
					/>
				</a-form-item>

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
		</a-skeleton>

		<template #footer>
			<a-button class="xn-mr8" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="sendLoading">发送</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="starAskForLeave">
	import { required } from '@/utils/formRules'

	import tool from '@/utils/tool'
	import { cloneDeep, range } from 'lodash-es'

	import bizProcessApi from '@/api/biz/bizProcessApi'
	import { useUserSelector } from '@/composables/useUserSelector'
	import { useProcessParam } from '@/composables/useProcessParam'
	import dayjs from '@/utils/dayjs'
	import { computed, createVNode, ref } from 'vue'
	import { Decimal } from 'decimal.js'
	import bizUserVacationApi from '@/api/biz/bizUserVacationApi'
	import { App, message } from 'ant-design-vue'
	import { useLoading } from '@/composables/useLoading'
	import bizSaleProjectModal from '@/views/biz/saleproject/modal/index.vue'
	import { hasPerm } from '@/utils/permission'
	import bizPurchaseOrderApi from '@/api/biz/bizPurchaseOrderApi'
	import supplierApi from '@/api/biz/supplierApi'
	const activeSelectObject = ref({})
	const { modal } = App.useApp()
	const categoryList = ref([])
	const sendLoading = ref(false)
	// 定义emit事件
	const emit = defineEmits({ successful: null })
	// 默认是关闭状态
	const visible = ref(false)
	const formRef = ref()
	// 表单数据
	const formData = ref({})

	const { copyUserIdList, approveUserIdList, treasurer } = useProcessParam('Process_ask_leave')
	const isBindProject = (str) => {
		return ['ProjectInstallation', 'AfterSalesService'].includes(str)
	}

	const allowEmpty = computed(() => {
		if (isBindProject(formData.value.category)) {
			return [false, true]
		}

		return [false, false]
	})

	watch(
		() => formData.value.category,
		() => {
			formData.value.objectId = ''
			activeSelectObject.value = {}
		}
	)

	const openProjectSelect = () => {
		let value = {}
		let content = createVNode(bizSaleProjectModal, {
			rowSelection: {
				type: 'radio',
				onSelect: (v) => {
					value = v
				},
				onChange: () => {}
			}
		})
		const onOk = () => {
			formData.value.objectId = value.id
			activeSelectObject.value = value
		}
		modal.confirm({
			icon: null,
			content: content,
			width: '1200px',
			onOk: onOk
		})
	}
	// 获取当前日期并加上一天

	// 格式化当前时间
	// 打开抽屉
	const {
		load: onOpen,
		loading,
		error
	} = useLoading(async (type) => {
		visible.value = true
		const res = await bizUserVacationApi.bizUserVacationDetail()
		categoryList.value = tool.dictListByPath('vacation', type).map((v) => {
			const amount = new Decimal(res.amount ? res.amount : 0)
			const usedAmount = new Decimal(res.usedAmount ? res.usedAmount : 0)
			const endAmount = amount.sub(usedAmount).toNumber()

			if (v.value === res.category) {
				v.endAmount = endAmount
				v.label = `${v.label}(剩余天数${endAmount})`
			}
			return v
		})
		formData.value = {
			approveUserIdList: approveUserIdList,
			copyUserIdList: copyUserIdList,
			treasurer: treasurer
		}
	})

	// 关闭抽屉
	const onClose = () => {
		emit('successful')
		visible.value = false
	}
	// 默认要校验的
	const checkTime = async (_rule, value) => {
		// if (!value) return Promise.reject('请假日期不能为空')
		if (!value) return Promise.reject('请假日期不能为空')

		if (value.length < 2) {
			return Promise.reject('请假日期不能为空')
		}

		const [startTime, endTime] = value
		// 解析日期字符串
		const startDateTime = dayjs(startTime)
		const endDateTime = dayjs(endTime)
		// 验证 startTime 小于 endTime，且不能等于 endTime
		if (startDateTime.isAfter(endDateTime)) {
			return Promise.reject('开始时间必须小于结束时间')
		}

		// 验证 startTime 必须在12点以后（包含12点）
		const startHour = startDateTime.hour()
		if (startHour > 12) {
			return Promise.reject('请假开始时间不能在下午！')
		}

		// 验证 endTime 不能在12点之前（可以包含12点）
		const endHour = endDateTime.hour()
		if (endHour < 12) {
			return Promise.reject('请假结束时间不能在8点！')
		}
		return Promise.resolve()
	}

	const formRules = computed(() => {
		let rule = {
			category: [required('请假类型不能为空')],
			createTime: [
				required(''),
				{
					validator: checkTime,
					trigger: 'blur'
				}
			]
		}
		if (isBindProject(formData.value.category)) {
			Object.assign(rule, {
				objectId: [required('单号不能为空')]
			})
		}

		return rule
	})

	const amount = computed(() => {
		if (!formData.value.createTime) {
			return ''
		}
		if (formData.value.createTime.length < 2) {
			return ''
		}
		const [startTime, endTime] = formData.value.createTime

		const startDateTime = dayjs(startTime)
		const endDateTime = dayjs(endTime)
		let day = endDateTime.diff(startDateTime, 'day') + 1

		if (startDateTime.hour() === 12 || endDateTime.hour() === 12) {
			day = day - 0.5
		}

		if (startDateTime.hour() === 12 && endDateTime.hour() === 12) {
			day = day - 0.5
		}
		return day
	})
	const amountLabel = computed(() => {
		return '日期:' + (amount.value ? `       (${amount.value?.toFixed(1)}) 天` : '')
	})

	// 验证并提交数据
	const onSubmit = async () => {
		try {
			await formRef.value.validate()
		} catch (e) {
			return
		}

		let form = cloneDeep(formData.value)

		const find = categoryList.value.find((v) => {
			return v.value === form.category
		})
		if (find && find.endAmount !== undefined) {
			if (find.endAmount < amount.value) {
				message.warn(`已经没有${find.label}类型的假期了！`)
				return
			}
		}

		sendLoading.value = true
		try {
			// createTime范围查询条件重载
			if (form.createTime) {
				form.startTime = form.createTime[0]
				form.endTime = form.createTime[1]
				delete form.createTime
			}
			form.amount = amount.value
			await bizProcessApi.bizProcessStartLeave(form)
			onClose()
		} finally {
			sendLoading.value = false
		}
	}
	// 传递设计器需要的API
	const selectorApiFunction = useUserSelector()

	const disabledDateTime = () => {
		const hours = range(0, 8).concat(range(19, 24)).concat(range(9, 18))

		return {
			disabledHours: () => {
				return hours.filter((v) => v !== 12)
			},

			disabledMinutes: () => range(1, 30).concat(range(30, 59)),
			disabledSeconds: () => [1, 59]
		}
	}

	// 调用这个函数将子组件的一些数据和方法暴露出去
	defineExpose({
		onOpen
	})
</script>
