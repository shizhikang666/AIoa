<template>
	<xn-form-container title="采购单导出" width="960px" v-model:open="open" :destroy-on-close="true" @close="onClose">
		<a-space direction="vertical">
			<a-card :bordered="false">
				<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
					<a-row :gutter="24">
						<a-col :span="12">
							<a-form-item label="所属组织：" name="orgId">
								<a-tree-select
									v-model:value="searchFormState.orgId"
									class="xn-wd"
									:dropdown-style="{ maxHeight: '400px', overflow: 'auto' }"
									placeholder="请选择组织"
									allow-clear
									:tree-data="treeData"
									:field-names="{
										children: 'children',
										label: 'name',
										value: 'id'
									}"
									selectable="false"
									tree-line
								></a-tree-select>
							</a-form-item>
						</a-col>
						<a-col :span="12">
							<a-form-item label="产品名称" name="productName">
								<a-input v-model:value="searchFormState.productName" />
							</a-form-item>
						</a-col>

						<a-col :span="12">
							<a-form-item label="结算状态" name="settlementStatus">
								<a-select
									v-model:value="searchFormState.settlementStatus"
									placeholder="请输入结算状态"
									:options="settlementStatusOptions"
								/>
							</a-form-item>
						</a-col>
						<a-col :span="12">
							<a-form-item label="入库状态" name="storageStatus">
								<a-select
									v-model:value="searchFormState.storageStatus"
									placeholder="入库状态"
									:options="storageOptions"
								/>
							</a-form-item>
						</a-col>
						<a-col :span="12">
							<a-form-item label="金额范围">
								<a-row>
									<a-space>
										<div>
											<a-form-item label="" name="minAmount">
												<XnCurrencyInput
													:min="0"
													v-model:value="searchFormState.minAmount"
													placeholder="请输入最小金额"
												/>
											</a-form-item>
										</div>

										<div>
											<a-form-item label="" name="maxAmount">
												<XnCurrencyInput
													:min="0"
													v-model:value="searchFormState.maxAmount"
													placeholder="请输入最大金额"
												/>
											</a-form-item>
										</div>
									</a-space>
								</a-row>
							</a-form-item>
						</a-col>

						<a-col :span="12">
							<a-form-item label="创建时间" name="createTime">
								<a-range-picker
									value-format="YYYY-MM-DD HH:mm:ss"
									v-model:value="searchFormState.createTime"
									show-time
								/>
							</a-form-item>
						</a-col>

						<a-col :span="12">
							<a-button :loading="loading" type="primary" @click="exportExcel">导出</a-button>
							<a-button style="margin: 0 8px" @click="reset">重置</a-button>
						</a-col>
					</a-row>
				</a-form>
			</a-card>
		</a-space>
	</xn-form-container>
</template>
<script setup name="purchaseOrderExport">
	import { useTemplateRef } from 'vue'
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import { useLoading } from '@/composables/useLoading'
	import { saveAs } from 'file-saver'
	import { Decimal } from 'decimal.js'
	import ExcelJS from 'exceljs'
	import { useOrg } from '@/composables/useOrg'
	import * as XLSX from 'xlsx'
	import bizPurchaseOrderApi from '@/api/biz/bizPurchaseOrderApi'
	const { treeData, loadingTreeData } = useOrg()
	const settlementStatusOptions = tool.dictListByPath(['PURCHASE_ORDER', 'SETTLEMENT_STATUS'])
	const storageOptions = tool.dictListByPath(['PURCHASE_ORDER', 'STORAGE_STATUS'])
	const open = ref(false)
	const searchFormState = ref({})
	const searchFormRef = useTemplateRef('searchFormRef')

	// 导出 Excel 文件
	const exportToExcel = async (res) => {
		console.log(res)
		// 数据处理函数
	}

	const exportPurchaseOrdersToExcel = async (res) => {
		// 计算成本函数（适配采购订单）
		const calculateTotalCost = (orderItems) => {
			if (!orderItems || orderItems.length === 0) return 0
			return orderItems.reduce((total, item) => {
				return total + (item.amount || 0)
			}, 0)
		}

		// 获取最大订单项数量
		const getMaxOrderItems = (data) => {
			let maxItems = 0
			data.forEach((order) => {
				const items = order.orderItems || []
				if (items.length > maxItems) {
					maxItems = items.length
				}
			})
			return maxItems
		}

		// 数据处理函数
		const processData = (res) => {
			const maxOrderItems = getMaxOrderItems(res)

			const allData = res.map((order) => {
				const orderItems = order.orderItems || []
				const totalCost = calculateTotalCost(orderItems)

				// 解析供应商信息
				let supplierInfo = {}
				try {
					supplierInfo = order.extJson ? JSON.parse(order.extJson) : {}
				} catch (e) {
					supplierInfo = {}
				}

				// 基础数据
				const baseData = [
					order.createTime || '', // 创建时间
					order.updateTime || '', // 更新时间
					order.createUserName || '', // 创建人
					order.id || '', // 采购单号
					order.title || '', // 采购标题
					order.amount || 0, // 采购总金额
					totalCost, // 订单项总金额
					order.settlementStatus || '', // 结算状态
					order.storageStatus || '', // 入库状态
					order.desirePurchaseDate || '', // 期望采购日期
					supplierInfo.supplier?.name || '', // 供应商名称
					supplierInfo.supplier?.contacts || '', // 供应商联系人
					order.remark || '', // 备注
					order.org || '' // 组织
				]

				// 构建订单项数据（每个订单项包含多个字段）
				const orderItemsData = []
				for (let i = 0; i < maxOrderItems; i++) {
					if (i < orderItems.length) {
						const item = orderItems[i]
						orderItemsData.push(
							item.productName || '', // 产品名称
							item.productId || '', // 产品ID
							item.number || 0, // 数量
							item.unitAmount || 0, // 单价
							item.amount || 0, // 总金额
							item.discountRate || 0, // 折扣率
							item.freightShareAmount || 0, // 分摊运费
							item.unitCostWithFreight || 0, // 含运费单价
							item.remark || '', // 订单项备注
							item.storageStatus || '' // 订单项入库状态
						)
					} else {
						// 补充空值（每个订单项10个字段）
						for (let j = 0; j < 10; j++) {
							orderItemsData.push('')
						}
					}
				}

				return [...baseData, ...orderItemsData]
			})

			return { data: allData, maxOrderItems }
		}

		const workbook = new ExcelJS.Workbook()
		const worksheet = workbook.addWorksheet('采购申请单')

		// 基础列标题
		const baseColumns = [
			'创建时间',
			'更新时间',
			'创建人',
			'采购单号',
			'采购标题',
			'采购总金额',
			'订单项总金额',
			'结算状态',
			'入库状态',
			'期望采购日期',
			'供应商名称',
			'供应商联系人',
			'备注',
			'组织'
		]

		// 订单项列标题（每个订单项10列）
		const itemColumns = []
		const processed = processData(res)
		const maxOrderItems = processed.maxOrderItems

		for (let i = 1; i <= maxOrderItems; i++) {
			itemColumns.push(
				`订单项${i}-产品名称`,
				`订单项${i}-产品ID`,
				`订单项${i}-数量`,
				`订单项${i}-单价`,
				`订单项${i}-总金额`,
				`订单项${i}-折扣率`,
				`订单项${i}-分摊运费`,
				`订单项${i}-含运费单价`,
				`订单项${i}-备注`,
				`订单项${i}-入库状态`
			)
		}

		// 完整的列标题
		const allColumns = [...baseColumns, ...itemColumns]

		// 计算列宽
		let maxLength = allColumns.map((v) => v.length)

		// 添加标题行
		worksheet.addRow(allColumns)

		// 设置标题行样式
		worksheet.getRow(1).eachCell((cell) => {
			cell.font = { bold: true, size: 11 }
			cell.alignment = { horizontal: 'center', vertical: 'middle' }
			cell.fill = {
				type: 'pattern',
				pattern: 'solid',
				fgColor: { argb: 'FF4472C4' }
			}
			cell.font = { bold: true, color: { argb: 'FFFFFFFF' } }
		})

		// 添加数据行并更新最大长度
		processed.data.forEach((row) => {
			row.forEach((value, index) => {
				const strValue = value?.toString() || ''
				if (strValue.length > maxLength[index]) {
					maxLength[index] = Math.min(strValue.length, 50)
				}
			})
			worksheet.addRow(row)
		})

		// 设置数据行样式
		worksheet.eachRow((row, rowNumber) => {
			if (rowNumber > 1) {
				row.eachCell((cell, colNumber) => {
					cell.alignment = {
						horizontal: 'left',
						vertical: 'middle',
						wrapText: true
					}

					// 为金额和数字列设置右对齐
					const colTitle = allColumns[colNumber - 1]
					if (
						colTitle &&
						(colTitle.includes('金额') ||
							colTitle.includes('单价') ||
							colTitle.includes('数量') ||
							colTitle.includes('运费'))
					) {
						cell.alignment = { horizontal: 'right', vertical: 'middle' }
						cell.numFmt = '#,##0.00'
					}

					// 为状态列设置特殊样式
					if (colTitle && (colTitle.includes('状态') || colTitle.includes('结算'))) {
						const value = cell.value?.toString() || ''
						if (value === 'COMPLETED') {
							cell.fill = {
								type: 'pattern',
								pattern: 'solid',
								fgColor: { argb: 'FFC6EFCE' }
							}
							cell.font = { color: { argb: 'FF006100' } }
						} else if (value === 'Canceled') {
							cell.fill = {
								type: 'pattern',
								pattern: 'solid',
								fgColor: { argb: 'FFFFC7CE' }
							}
							cell.font = { color: { argb: 'FF9C0006' } }
						} else if (value === 'NOT_COMPLETED') {
							cell.fill = {
								type: 'pattern',
								pattern: 'solid',
								fgColor: { argb: 'FFFFEB9C' }
							}
							cell.font = { color: { argb: 'FF9C6500' } }
						}
					}

					// 为入库状态设置样式
					if (colTitle && colTitle.includes('入库状态')) {
						const value = cell.value?.toString() || ''
						if (value === 'IN_WAREHOUSE') {
							cell.fill = {
								type: 'pattern',
								pattern: 'solid',
								fgColor: { argb: 'FFC6EFCE' }
							}
						} else if (value === 'NOT_IN_WAREHOUSE') {
							cell.fill = {
								type: 'pattern',
								pattern: 'solid',
								fgColor: { argb: 'FFFFC7CE' }
							}
						}
					}
				})
			}
		})

		// 设置列宽
		worksheet.columns = allColumns.map((col, i) => {
			let width = Math.min(Math.max(maxLength[i] + 3, 12), 35)

			// 根据列类型设置特定宽度
			if (col.includes('产品名称')) width = 25
			if (col.includes('产品ID') || col.includes('采购单号')) width = 22
			if (col.includes('备注')) width = 30
			if (col.includes('地址')) width = 35
			if (col.includes('时间') || col.includes('日期')) width = 20
			if (col.includes('金额') || col.includes('单价')) width = 15

			return {
				header: col,
				key: col,
				width: width
			}
		})

		// 设置行高
		worksheet.eachRow((row, rowNumber) => {
			if (rowNumber === 1) {
				row.height = 35
			} else {
				let maxLines = 1
				row.eachCell((cell) => {
					if (cell.value && cell.value.toString().includes('\n')) {
						const lines = cell.value.toString().split('\n').length
						maxLines = Math.max(maxLines, lines)
					}
				})
				row.height = Math.max(22, maxLines * 16)
			}
		})

		// 冻结标题行和基础列
		worksheet.views = [
			{
				state: 'frozen',
				xSplit: baseColumns.length,
				ySplit: 1,
				topLeftCell: `${String.fromCharCode(65 + baseColumns.length)}2`,
				activeCell: `${String.fromCharCode(65 + baseColumns.length)}2`
			}
		]

		// 添加表头筛选
		worksheet.autoFilter = {
			from: 'A1',
			to: `${String.fromCharCode(64 + allColumns.length)}${processed.data.length + 1}`
		}

		// 生成 Excel 文件
		const buffer = await workbook.xlsx.writeBuffer()
		const blob = new Blob([buffer], {
			type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
		})

		// 使用 file-saver 保存文件
		saveAs(blob, `采购申请单导出_${new Date().getTime()}.xlsx`)
	}

	const { load: exportExcel, loading } = useLoading(async () => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// createTime范围查询条件重载
		if (searchFormParam.createTime) {
			searchFormParam.startCreateTime = searchFormParam.createTime[0]
			searchFormParam.endCreateTime = searchFormParam.createTime[1]
			delete searchFormParam.createTime
		}
		const result = await bizPurchaseOrderApi.bizPurchaseOrderDetailList(searchFormParam)

		await exportPurchaseOrdersToExcel(result)
	})
	const reset = () => {
		searchFormRef.value.resetFields()
	}

	const onOpen = async (record) => {
		await loadingTreeData()
		open.value = true
	}
	const onClose = () => {
		searchFormRef.value.resetFields()
		open.value = false
	}
	defineExpose({
		onOpen
	})
</script>

<style scoped></style>
