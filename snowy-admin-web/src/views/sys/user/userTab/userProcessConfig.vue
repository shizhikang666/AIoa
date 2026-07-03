<template>
	<a-tabs v-model:activeKey="activeKey" tab-position="left">
		<a-tab-pane v-for="(item, i) in processConfigList" :key="item.key" :tab="item.name">
			<TheProcessForm
				:open="item.open"
				:processKey="item.key"
				v-model:approve-user-id-list="item.approveUserIdList"
				v-model:copy-user-id-list="item.copyUserIdList"
				v-model:treasurer="item.treasurer"
				v-model:procure="item.procure"
				:show-procure="item.showProcure"
				:show-treasurer="item.showTreasurer"
				:show-open="false"
			>
			</TheProcessForm>
		</a-tab-pane>
	</a-tabs>
	<a-row justify="center" align="middle" style="padding-top: 20px">
		<a-button type="primary" :loading="submitLoading" @click="onSubmit()">保存</a-button>
		<a-button class="xn-ml10" @click="rest">重置</a-button>
	</a-row>
</template>
<script setup lang="js">
	import TheProcessForm from '@/views/sys/config/processConfig/components/theProcessForm.vue'
	import { useLoading } from '@/composables/useLoading'
	import { cloneDeep } from 'lodash-es'
	import tool from '@/utils/tool'
	import userCenterApi from '@/api/sys/userCenterApi'

	const activeKey = ref('Process_reimbursement')
	const processConfigList = ref([
		{
			key: 'Process_reimbursement',
			name: '报销流程',
			showTreasurer: true
		},

		{
			key: 'Process_make_payment',
			name: '付款申请',
			showTreasurer: true
		},
		{
			key: 'Process_project_reissue_product',
			name: '项目补货流程',
			showOpen: true
		},
		{
			key: 'Process_sale_project_play',
			name: '项目收款进度',
			showTreasurer: true
		},
		{
			key: 'Process_payment',
			name: '收入流程',
			showTreasurer: true
		},

		{
			key: 'Process_sale_project_init',
			name: '项目初始流程',
			showOpen: true
		},
		{
			key: 'Process_sale_project_delivery',
			name: '销售项目出库流程',
			showOpen: true
		},
		{
			key: 'Process_procure',
			name: '采购申请',
			showProcure: true
		},
		{
			key: 'Process_ask_leave',
			name: '请假出差申请'
		}
	])
	const rest = () => {
		let cloeConfig = cloneDeep(tool.data.get('SYS_USER_PROCESS_CONFIG'))

		processConfigList.value.forEach((v) => {
			let find = cloeConfig.config.find((f) => f.processName == v.key)
			const sysFind = cloneDeep(tool.data.get('SYS_CONFIG').processConfigMap[v.key])
			find = find ? find : { processName: v.key }
			v = Object.assign(v, nonNullAssign(find, sysFind))
		})
	}
	const getConfig = async () => {
		userCenterApi.userCenterGetProcessConfig().then((res) => {
			tool.data.set('SYS_USER_PROCESS_CONFIG', res)
			const result = cloneDeep(tool.data.get('SYS_USER_PROCESS_CONFIG'))
			processConfigList.value.forEach((v) => {
				const sysFind = cloneDeep(tool.data.get('SYS_CONFIG').processConfigMap[v.key])
				let find = result.config.find((f) => f.processName === v.key)
				find = find ? find : { processName: v.key }

				v = Object.assign(v, { open: true }, nonNullAssign(find, sysFind))
			})
		})
	}

	const { loading: submitLoading, load: onSubmit } = useLoading(async () => {
		const param = cloneDeep(processConfigList.value).map((item) => serializeProcessConfig(item))

		await userCenterApi.userCenterEditProcessConfig({
			config: param
		})
		await getConfig()
	})

	getConfig()

	function nonNullAssign(target, source) {
		return Object.entries(source).reduce((acc, [key, value]) => {
			if (value !== null && value !== undefined && value !== '') {
				if (Array.isArray(value) && value.length === 0) {
					// 如果源对象中的属性是数组且长度为 0，则不进行覆盖
					return acc
				}
				acc[key] = value
			}
			return acc
		}, target)
	}

	function serializeProcessConfig(item) {
		const result = {
			processName: item.processName || item.key,
			approveUserIdList: toUserIdList(item.approveUserIdList),
			copyUserIdList: toUserIdList(item.copyUserIdList),
			treasurer: toSingleUserId(item.treasurer),
			procure: toSingleUserId(item.procure)
		}
		if (item.open !== undefined) {
			result.open = Boolean(item.open)
		}
		return result
	}

	function toUserIdList(value) {
		if (value === null || value === undefined || value === '') {
			return []
		}
		if (typeof value === 'string') {
			const trimmed = value.trim()
			if (!trimmed) {
				return []
			}
			try {
				const parsed = JSON.parse(trimmed)
				if (Array.isArray(parsed) || (parsed && typeof parsed === 'object')) {
					return toUserIdList(parsed)
				}
			} catch {
				// keep plain id strings supported
			}
			return trimmed.split(/[\s,]+/).filter(Boolean)
		}
		const items = Array.isArray(value) ? value : [value]
		return Array.from(new Set(items.map((item) => userIdFromSelection(item)).filter(Boolean)))
	}

	function toSingleUserId(value) {
		return toUserIdList(value)[0] || ''
	}

	function userIdFromSelection(value) {
		if (value === null || value === undefined) {
			return ''
		}
		if (Array.isArray(value)) {
			return userIdFromSelection(value[0])
		}
		if (typeof value === 'object') {
			for (const key of ['userId', 'id', 'value', 'USER_ID', 'ID']) {
				const result = userIdFromSelection(value[key])
				if (result) {
					return result
				}
			}
			return ''
		}
		return String(value).trim()
	}
</script>

<style scoped></style>
