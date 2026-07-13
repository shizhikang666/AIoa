<template>
	<xn-form-container title="修改流程" :width="550" :visible="visible" :destroy-on-close="true" @close="onClose">
		<a-skeleton active :loading="loading">
			<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
				<a-form-item label="请假类型：" name="category">
					<a-select
						placeholder="选择假期类型"
						:value="$TOOL.dictTypeDataByPath('vacation', formData.category)"
						disabled
					></a-select>
				</a-form-item>

				<a-form-item v-if="isBindProject(formData.category)" label="项目成交单：" name="objectId">
					<a-typography-link :type="formData.objectId ? '' : ''">
						{{ formData.objectId }}
					</a-typography-link>
				</a-form-item>
				<a-form-item :label="amountLabel" name="createTime">
					<a-range-picker
						showToday
						:disabled="[true, false]"
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
						disabled
						:org-tree-api="selectorApiFunction.orgTreeApi"
						:user-page-api="selectorApiFunction.userPageApi"
						:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
						data-type="object"
						:addShow="false"
						:closeShow="false"
						v-model:value="formData.approveUserIdList"
					/>
				</a-form-item>
				<a-form-item label="抄送人：" name="copyUserIdList">
					<xn-user-selector
						:closeShow="false"
						:org-tree-api="selectorApiFunction.orgTreeApi"
						:user-page-api="selectorApiFunction.userPageApi"
						:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
						data-type="object"
						disabled
						:addShow="false"
						v-model:value="formData.copyUserIdList"
					/>
				</a-form-item>
			</a-form>
		</a-skeleton>

		<template #footer>
			<a-button class="xn-mr8" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="sendLoading">确认</a-button>
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
	} = useLoading(async (record) => {
		const fields = [
			'startTime',
			'endTime',
			'remark',
			'amount',
			'category',
			'initiator',
			'objectId',
			'approveUserIdList',
			'copyUserIdList'
		]
		const res = await bizProcessApi.bizVariable({ id: record.id, fields })
		const result = {}
		res.forEach((item) => {
			result[item.name] = item.value
		})
		let array = []
		if (result.startTime) {
			array.push(result.startTime)
			delete result.startTime
		}

		if (result.endTime) {
			array.push(result.endTime)
			delete result.endTime
		}

		result.createTime = array

		formData.value = result
		formData.value.id = record.id

		visible.value = true
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
		sendLoading.value = true
		try {
			// createTime范围查询条件重载
			if (form.createTime) {
				form.startTime = form.createTime[0]
				form.endTime = form.createTime[1]
				delete form.createTime
			}
			form.amount = amount.value
			await bizProcessApi.bizProcessEditLeave(form)
			emit('successful')
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
