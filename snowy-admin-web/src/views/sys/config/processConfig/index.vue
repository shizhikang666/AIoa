<template>
	<a-tabs v-model:activeKey="activeKey" tab-position="left">
		<a-tab-pane v-for="(item, i) in processConfigList" :key="item.key" :tab="item.name">
			<TheProcessForm
				v-model:approve-user-id-list="config.processConfigMap[item.key].approveUserIdList"
				v-model:copy-user-id-list="config.processConfigMap[item.key].copyUserIdList"
				v-model:open="config.processConfigMap[item.key].open"
				v-model:treasurer="config.processConfigMap[item.key].treasurer"
				v-model:procure="config.processConfigMap[item.key].procure"
				process-key="Process_sale_project_play"
				:show-treasurer="item.showTreasurer"
				:show-open="item.showOpen"
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
	import SysConfigApi from '@/api/sys/sysConfigApi'
	import TheProcessForm from '@/views/sys/config/processConfig/components/theProcessForm.vue'
	import { useLoading } from '@/composables/useLoading'
	import { cloneDeep } from 'lodash-es'
	import tool from '@/utils/tool'

	const activeKey = ref('Process_reimbursement')
	const config = ref({
		processConfigMap: {
			Process_reimbursement: {},
			Process_sale_project_play: {},
			Process_sale_project_init: {},
			Process_sale_project_delivery: {},
			Process_procure: {},
			Process_project_reissue_product: {},
			Process_make_payment: {},
			Process_payment: {},
			Process_procure_in_warehouse: {},
			Process_sale_project_product_return: {},
			Process_ask_leave: {}
		}
	})

	Object.keys(config.value.processConfigMap).forEach((key) => {
		config.value.processConfigMap[key] = Object.assign({
			open: true,
			approveUserIdList: [],
			copyUserIdList: [],
			treasurer: ''
		})
	})

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
			key: 'Process_payment',
			name: '收入流程',
			showTreasurer: true
		},
		{
			key: 'Process_procure_in_warehouse',
			showOpen: true,
			name: '采购入库'
		},
		{
			key: 'Process_sale_project_product_return',
			showOpen: true,
			name: '销售退货'
		},
		{
			key: 'Process_ask_leave',
			name: '请假出差申请'
		}
	])
	const rest = () => {
		let cloeConfig = cloneDeep(tool.data.get('SYS_CONFIG'))
		config.value.processConfigMap = Object.assign(config.value.processConfigMap, cloeConfig.processConfigMap)
	}
	const getConfig = async () => {
		SysConfigApi.sysConfigDetail().then((res) => {
			tool.data.set('SYS_CONFIG', res)
			config.value = cloneDeep(tool.data.get('SYS_CONFIG'))
		})
	}
	const { loading: submitLoading, load: onSubmit } = useLoading(async () => {
		const param = cloneDeep(config.value)
		await SysConfigApi.saveConfig({ config: param })
		await getConfig()
	})

	rest()
</script>

<style scoped></style>
