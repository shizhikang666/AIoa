import SettlementAccountApi from '@/api/biz/settlementAccountApi'
import { useLoading } from '@/composables/useLoading'
import { createVNode, ref } from 'vue'
import bizpurchaseorderModel from '@/views/biz/bizpurchaseorder/model/index.vue'
import bizSaleProjectModal from '@/views/biz/saleproject/modal/index.vue'
import bizCollectionReceiptModel from '@/views/biz/bizcollectionreceipt/model/index.vue'
import { Decimal } from 'decimal.js'
import returnOrderModel from '@/views/biz/returnorder/model/index.vue'

export function useSettlementAccount() {
	const accountList = ref([])
	const loadSettlementAccountApi = useLoading(async () => {
		const res = await SettlementAccountApi.settlementAccountList()
		accountList.value = res.map((v) => {
			return {
				label: v.accountName,
				value: v.id
			}
		})
	})

	const openProcureFlowSelect =
		({ modal, onOk }) =>
		() => {
			let value = {}
			let content = createVNode(bizpurchaseorderModel, {
				disableSearchFromKey: {
					settlementStatus: true,
					storageStatus: true,
					supplierId: false,
					createTime: false
				},

				defaultSearchFrom: {
					settlementStatus: 'NOT_COMPLETED'
				},
				rowSelection: {
					type: 'radio',
					onSelect: (v) => {
						value = v
					},
					onChange: () => {}
				}
			})

			modal.confirm({
				icon: null,
				content: content,
				width: '1000px',
				onOk: () => {
					onOk ? onOk(value) : null
				}
			})
		}

	const openProjectSelect =
		({ modal, onOk }) =>
		() => {
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

			modal.confirm({
				icon: null,
				content: content,
				width: '1200px',
				onOk: () => {
					onOk ? onOk(value) : null
				}
			})
		}
	//代收款还款
	const openCollectionReceipt =
		({ modal, onOk }) =>
		() => {
			let value = {}
			let content = createVNode(bizCollectionReceiptModel, {
				disableSearchFromKey: {
					playStatus: true,
					createTime: false
				},

				defaultSearchFrom: {
					playStatus: 'Unsettled'
				},
				rowSelection: {
					type: 'radio',
					onSelect: (v) => {
						value = v
					},
					onChange: () => {}
				}
			})

			modal.confirm({
				icon: null,
				content: content,
				width: '1000px',
				onOk: () => {
					onOk ? onOk(value) : null
				}
			})
		}
	//退货单
	const openReturnOrder =
		({ modal, onOk }) =>
		() => {
			let value = {}
			let content = createVNode(returnOrderModel, {
				disableSearchFromKey: {
					createTime: false
				},
				defaultSearchFrom: {
					state: 'Unsettled',
					warehouseState: 'RECEIVED'
				},
				rowSelection: {
					type: 'radio',
					onSelect: (v) => {
						value = v
					},
					onChange: () => {}
				}
			})
			modal.confirm({
				icon: null,
				content: content,
				width: '1000px',
				onOk: () => {
					onOk ? onOk(value) : null
				}
			})
		}

	return {
		accountList,
		loadSettlementAccountApi,
		openProcureFlowSelect,
		openProjectSelect,
		openCollectionReceipt,
		openReturnOrder
	}
}
