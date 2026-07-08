<template>
	<xn-form-container title="销售项目导出" width="960px" v-model:open="open" :destroy-on-close="true" @close="onClose">
		<a-space direction="vertical">
			<a-card :bordered="false">
				<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
					<a-row :gutter="24">
						<a-col :span="12">
							<a-form-item label="项目名称" name="projectName">
								<a-input v-model:value="searchFormState.projectName" placeholder="请输入项目名称" />
							</a-form-item>
						</a-col>

						<a-col :span="12">
							<a-form-item label="项目编号" name="projectCode">
								<a-input v-model:value="searchFormState.projectCode" placeholder="请输入项目编号" />
							</a-form-item>
						</a-col>
						<a-col :span="12">
							<a-form-item label="项目状态" name="projectState">
								<a-select
									mode="multiple"
									v-model:value="searchFormState.projectState"
									placeholder="请选择项目状态"
									:options="projectStateOptions"
								/>
							</a-form-item>
						</a-col>
						<a-col :span="12">
							<a-form-item label="付款状态" name="playState">
								<a-select
									mode="multiple"
									v-model:value="searchFormState.playState"
									placeholder="请选择付款状态"
									:options="playStateOptions"
								/>
							</a-form-item>
						</a-col>

						<a-col :span="12">
							<a-form-item label="项目显示状态" name="visibility">
								<a-select
									v-model:value="searchFormState.visibility"
									placeholder="请选择项目显示状态"
									:options="visibilityOptions"
								/>
							</a-form-item>
						</a-col>
						<!--				<a-col :span="6" v-show="advanced">-->
						<!--					<a-form-item label="累计金额" name="totalPrice">-->
						<!--						<a-input v-model:value="searchFormState.totalPrice" placeholder="请输入累计金额" />-->
						<!--					</a-form-item>-->
						<!--				</a-col>-->
						<!--				<a-col :span="6" v-show="advanced">-->
						<!--					<a-form-item label="累计收款金额" name="amountCollected">-->
						<!--						<a-input v-model:value="searchFormState.amountCollected" placeholder="请输入累计收款金额" />-->
						<!--					</a-form-item>-->
						<!--				</a-col>-->
						<a-col :span="12">
							<a-form-item label="类别直采" name="projectCategory">
								<a-select
									v-model:value="searchFormState.projectCategory"
									placeholder="请选择类别直采||默认"
									:options="projectCategoryOptions"
								/>
							</a-form-item>
						</a-col>
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
							<a-form-item label="项目负责人" name="user">
								<a-input v-model:value="searchFormState.user" placeholder="请输入项目负责人" />
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
							<a-form-item label="成交时间" name="completionTime">
								<a-range-picker
									value-format="YYYY-MM-DD HH:mm:ss"
									v-model:value="searchFormState.completionTime"
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
			<a-card>
				<a-col :span="12">
					<a-form-item label="所属组织：" name="orgId">
						<a-tree-select
							v-model:value="searchNotPayFormState.orgId"
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

				<a-button @click="exportNotPlayExcel" type="primary" :loading="loadingNotPlay">导出未回款明细</a-button>
			</a-card>
		</a-space>
	</xn-form-container>
</template>
<script setup name="projectExport">
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
	const open = ref(false)
	const searchFormState = ref({})
	const searchFormRef = useTemplateRef('searchFormRef')
	const projectStateOptions = tool.dictListByPath(['SALE_PROJECT', 'SALE_PROJECT_STATE'])
	const playStateOptions = tool.dictListByPath('SALE_PROJECT', 'SALE_PROJECT_PLAY_STATE')
	const visibilityOptions = tool.dictListByPath('SALE_PROJECT', 'SALE_PROJECT_VISIBILITY')
	const projectCategoryOptions = tool.dictListByPath('SALE_PROJECT', 'PROJECT_CATEGORY')
	const isCostPerm = hasPerm('bizSaleProjectCost')
	const searchNotPayFormState = ref({})
	const calculateTotalCost = (data) => {
		let totalCost = new Decimal(0) // 改为 Decimal 类型

		function computeItemCost(item) {
			// 如果是套件，递归计算子项成本总和
			if (item.productSysCategory === 'KIT_PRODUCT' && Array.isArray(item.children) && item.children.length > 0) {
				let kitCost = new Decimal(0)
				for (const child of item.children) {
					// 从 child.extJson 中解析出 productPurchasePrice 和 number
					if (child.extJson) {
						try {
							const ext = JSON.parse(child.extJson)
							const product = ext.product

							const purchasePrice = product?.purchasePrice ? product.purchasePrice : 0
							kitCost = kitCost.add(new Decimal(purchasePrice).mul(new Decimal(child.number || 1)))
						} catch (e) {
							console.error('解析 extJson 失败', e)
						}
					}
				}
				return kitCost.mul(new Decimal(item.number || 1))
			} else {
				// 单品直接用 productPurchasePrice * number
				return new Decimal(item.productPurchasePrice || 0).mul(item.number || 1)
			}
		}

		for (const item of data) {
			const res = computeItemCost(item)
			totalCost = totalCost.add(res) // 使用 Decimal 的 add 方法
		}

		return totalCost
	}

	// 导出 Excel 文件
	const exportToExcel = async (res) => {
		// 数据处理函数
		const processData = (res) => {
			// 收集所有跟进记录，确定最大列数
			let maxFollowUps = 0

			// 提前遍历计算最大跟进记录数
			res.forEach((record) => {
				const followUps = record.saleProjectFollowUps || []
				if (followUps.length > maxFollowUps) {
					maxFollowUps = followUps.length
				}
			})

			const allData = res.map((record) => {
				let v = record.bizSaleProject
				v.totalPrice = v.totalPrice || 0
				v.rebateAmount = v.rebateAmount || 0
				let result = new Decimal(v.totalPrice).sub(new Decimal(v.rebateAmount)).toString()
				let result2 = new Decimal(v.totalPrice).sub(new Decimal(v.amountCollected)).add(new Decimal(v.totalReturnAmount)).toString()
				let area = v.area ? v.area.split('/') : ['', '', '', '']

				let logisticsCategory = record.invoiceList
					.map((invoice) => tool.dictTypeDataByPath('LOGISTICS_CATEGORY', invoice.logisticsCategory))
					.join(',')

				let logisticsId = record.invoiceList.map((invoice) => invoice.logisticsId).join(',')

				let products = record.productItems.map((v) => v.productName).join(',')
				let accountNames = record.paymentRecords ? record.paymentRecords.map((v) => v.accountName).join(',') : ''
				let drawerUnit = record.invoicingList.map((v) => v.customerCompany).join(',')
				// 处理跟进记录
				const followUps = record.saleProjectFollowUps || []
				let money = calculateTotalCost(record.productItems)

				let chengbeng = money.toString()
				let lirun = new Decimal(result).sub(money).toString()
				let lirunbaifenbi = new Decimal(lirun).div(result).mul(100).toFixed(2) + '%'
				// 基础数据
				const baseData = [
					v.createTime, // 创建日期
					v.completionDate,
					v.headName,
					v.projectCode,
					v.projectName, // 项目名称
					isCostPerm ? chengbeng : '无权限', // 成本
					isCostPerm ? lirun : '无权限', // 毛利
					isCostPerm ? lirunbaifenbi : '无权限', // 毛利率
					result, // 回扣后金额
					v.totalPrice, // 回扣前金额
					v.rebateAmount, // 回扣金额
					v.amountCollected, // 累计收款金额
					result2, // 未付款金额
					accountNames, // 收款账户
					area[0],
					area[1],
					v.address,
					v.consignee,
					v.phone,
					drawerUnit,
					v.unit,
					products,
					tool.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_PLAY_STATE', v.playState),
					logisticsCategory,
					logisticsId,
					v.remark
				]

				// 构建跟进记录数据
				const followUpData = []
				for (let i = 0; i < maxFollowUps; i++) {
					if (i < followUps.length) {
						const follow = followUps[i]
						// 每个跟进记录拆分为4列
						followUpData.push(
							follow.followUpTime ? formatDateTime(follow.followUpTime) : '', // 跟进时间
							follow.createUserName || '', // 跟进人
							tool.dictTypeDataByPath('SALE_PROJECT', 'FOLLOW_UP_CATEGORY', follow.category) || '', // 跟进类型
							follow.content || '' // 跟进内容
						)
					} else {
						// 补充空值
						followUpData.push('', '', '', '')
					}
				}

				// 合并基础数据和跟进记录数据
				return [...baseData, ...followUpData]
			})

			return { data: allData, maxFollowUps }
		}

		// 时间格式化函数
		const formatDateTime = (dateTime) => {
			if (!dateTime) return ''
			try {
				const date = new Date(dateTime)
				return (
					date.toLocaleDateString('zh-CN') +
					' ' +
					date.toLocaleTimeString('zh-CN', {
						hour12: false,
						hour: '2-digit',
						minute: '2-digit'
					})
				)
			} catch (e) {
				return dateTime
			}
		}

		const workbook = new ExcelJS.Workbook()
		const worksheet = workbook.addWorksheet('Sheet1')

		// 基础列标题
		const baseColumns = [
			'创建日期',
			'成交日期',
			'业务员',
			'项目编号',
			'项目名称',
			'成本',
			'毛利',
			'毛利率',
			'回扣后金额',
			'回扣前金额',
			'回扣金额',
			'累计收款金额',
			'未付款金额',
			'收款账户',
			'省份',
			'地区',
			'收货地址',
			'收货人',
			'收货电话',
			'开票客户公司',
			'终端客户名称',
			'销售产品',
			'收款状态',
			'物流',
			'物流单',
			'备注'
		]

		// 处理数据，获取最大跟进记录数
		const processed = processData(res)
		const data = processed.data
		const maxFollowUps = processed.maxFollowUps

		// 动态添加跟进记录列（每个跟进记录4列）
		const followUpColumns = []
		for (let i = 1; i <= maxFollowUps; i++) {
			followUpColumns.push(`跟进${i}-时间`, `跟进${i}-跟进人`, `跟进${i}-类型`, `跟进${i}-内容`)
		}

		// 完整的列标题
		const column = [...baseColumns, ...followUpColumns]

		let maxLength = column.map((v) => {
			return v.length
		})

		// 添加标题行
		worksheet.addRow(column)

		// 设置标题行样式
		worksheet.getRow(1).eachCell((cell) => {
			cell.font = { bold: true }
			cell.alignment = { horizontal: 'center', vertical: 'middle' }
			cell.fill = {
				type: 'pattern',
				pattern: 'solid',
				fgColor: { argb: 'FFE0E0E0' }
			}
		})

		// 添加数据行并更新最大长度
		data.forEach((row) => {
			row.forEach((v, index) => {
				if (v && v.toString().length > maxLength[index]) {
					maxLength[index] = v.toString().length
				}
			})
			worksheet.addRow(row)
		})

		// 设置数据行样式
		worksheet.eachRow((row, rowNumber) => {
			if (rowNumber > 1) {
				row.eachCell((cell) => {
					cell.alignment = {
						horizontal: 'left',
						vertical: 'middle',
						wrapText: true // 允许换行，特别是跟进内容可能较长
					}
					// 为跟进记录列添加特殊样式
					if (cell.col > baseColumns.length) {
						// 根据列类型设置不同样式
						const followUpColIndex = cell.col - baseColumns.length - 1
						const followUpType = followUpColIndex % 4

						switch (followUpType) {
							case 0: // 时间列
								cell.fill = {
									type: 'pattern',
									pattern: 'solid',
									fgColor: { argb: 'FFF0FFFF' }
								}
								break
							case 1: // 跟进人列
								cell.fill = {
									type: 'pattern',
									pattern: 'solid',
									fgColor: { argb: 'FFF0F8FF' }
								}
								break
							case 2: // 类型列
								cell.fill = {
									type: 'pattern',
									pattern: 'solid',
									fgColor: { argb: 'FFF5F5F5' }
								}
								break
							case 3: // 内容列
								cell.fill = {
									type: 'pattern',
									pattern: 'solid',
									fgColor: { argb: 'FFFAF0E6' }
								}
								break
						}
					}
				})
			}
		})

		// 设置列宽
		worksheet.columns = column.map((col, i) => {
			let width = Math.min(Math.max(maxLength[i] + 5, 10), 50)

			// 根据列类型设置不同宽度
			if (col.includes('-')) {
				const [_, colType] = col.split('-')
				switch (colType) {
					case '时间':
						width = 20
						break
					case '跟进人':
						width = 15
						break
					case '类型':
						width = 15
						break
					case '内容':
						width = 40
						break
				}
			}

			return {
				header: col,
				key: col,
				width: width
			}
		})

		// 设置行高
		worksheet.eachRow((row, rowNumber) => {
			if (rowNumber === 1) {
				// 标题行
				row.height = 30
			} else {
				// 数据行，根据内容调整高度
				let maxLines = 1
				row.eachCell((cell) => {
					if (cell.value && cell.value.toString().includes('\n')) {
						const lines = cell.value.toString().split('\n').length
						maxLines = Math.max(maxLines, lines)
					}
				})
				row.height = Math.max(25, maxLines * 18)
			}
		})

		// // 冻结标题行和基础列
		// worksheet.views = [
		// 	{
		// 		state: 'frozen',
		// 		xSplit: baseColumns.length, // 冻结基础列，方便查看跟进记录时基础信息固定
		// 		ySplit: 1, // 冻结标题行
		// 		topLeftCell: 'V2', // 从第2行第V列开始滚动（跟进记录区域）
		// 		activeCell: 'V2'
		// 	}
		// ]

		// 生成 Excel 文件
		const buffer = await workbook.xlsx.writeBuffer()
		const file = new Blob([buffer], { type: 'application/octet-stream' })
		saveAs(file, `销售项目导出_${new Date().getTime()}.xlsx`)
	}
	// 导出 Excel 文件
	const exportToExcel2 = async (res) => {
		// 数据处理函数
		const processData = (res) => {
			return res.map((record) => {
				let v = record.bizSaleProject
				v.totalPrice = v.totalPrice || 0
				v.rebateAmount = v.rebateAmount || 0
				let result = new Decimal(v.totalPrice).sub(new Decimal(v.rebateAmount)).toString()
				let area = v.area ? v.area.split('/') : ['', '', '', '']

				let logisticsCategory = record.invoiceList
					.map((invoice) => tool.dictTypeDataByPath('LOGISTICS_CATEGORY', invoice.logisticsCategory))
					.join(',')

				let logisticsId = record.invoiceList.map((invoice) => invoice.logisticsId).join(',')

				let products = record.productItems.map((v) => v.productName).join(',')
				let accountNames = record.paymentRecords ? record.paymentRecords.map((v) => v.accountName).join(',') : ''

				let drawerUnit = record.invoicingList.map((v) => v.customerCompany).join(',')
				return [
					v.createTime,
					v.completionDate,
					v.headName,
					v.projectCode,
					v.projectName,
					result,
					v.totalPrice,
					v.rebateAmount,
					accountNames,
					area[0],
					area[1],
					v.address,
					v.consignee,
					v.phone,
					drawerUnit,
					v.unit,
					products,
					tool.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_PLAY_STATE', v.playState),
					logisticsCategory,
					logisticsId,
					v.remark
				]
			})
		}
		const workbook = new ExcelJS.Workbook()
		const worksheet = workbook.addWorksheet('Sheet1')
		// 定义列标题
		const column = [
			'创建日期',
			'成交日期',
			'业务员',
			'项目编号',
			'项目名称',
			'回扣后金额',
			'回扣前金额',
			'回扣金额',
			'收款账户',
			'省份',
			'地区',
			'收货地址',
			'收货人',
			'收货电话',
			'开票客户公司',
			'终端客户名称',
			'销售产品',
			'收款状态',
			'物流',
			'物流单',
			'备注'
		]
		let maxLength = column.map((v) => {
			return v.length
		})
		// 添加标题行
		worksheet.addRow(column)

		// 设置标题行样式
		worksheet.getRow(1).eachCell((cell) => {
			cell.font = { bold: true } // 加粗
			cell.alignment = { horizontal: 'center', vertical: 'middle' } // 居中
		})

		// 添加数据行
		const data = processData(res)
		data.forEach((row) => {
			row.forEach((v, index) => {
				if (v && v.length > maxLength[index]) {
					maxLength[index] = v.length
				}
			})
			worksheet.addRow(row)
		})

		// 设置数据行样式
		worksheet.eachRow((row, rowNumber) => {
			if (rowNumber > 1) {
				// 跳过标题行
				row.eachCell((cell) => {
					cell.alignment = { horizontal: 'center', vertical: 'middle' } // 居中
				})
			}
		})

		// 设置列宽
		worksheet.columns = column.map((col, i) => ({
			header: col,
			key: col,
			width: maxLength[i] + 20 // 动态列宽
		}))

		// 设置行高
		worksheet.eachRow((row) => {
			row.height = 20 // 固定行高
		})

		// 生成 Excel 文件
		const buffer = await workbook.xlsx.writeBuffer()
		const file = new Blob([buffer], { type: 'application/octet-stream' })
		saveAs(file, '销售项目未回款明细.xlsx')
	}

	const { load: exportExcel, loading } = useLoading(async () => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// createTime范围查询条件重载
		if (searchFormParam.createTime) {
			searchFormParam.startCreateTime = searchFormParam.createTime[0]
			searchFormParam.endCreateTime = searchFormParam.createTime[1]
			delete searchFormParam.createTime
		}

		if (searchFormParam.completionTime) {
			searchFormParam.startCompletionTime = searchFormParam.completionTime[0]
			searchFormParam.endCompletionTime = searchFormParam.completionTime[1]
			delete searchFormParam.completionTime
		}

		if (searchFormParam.playState) {
			searchFormParam.playState = searchFormParam.playState.join(',')
		}

		if (searchFormParam.projectState) {
			searchFormParam.projectState = searchFormParam.projectState.join(',')
		}

		const res = await bizSaleProjectApi.bizSaleProjectListDetail(searchFormParam)
		await exportToExcel(res)
	})

	const { load: exportNotPlayExcel, loading: loadingNotPlay } = useLoading(async () => {
		const searchFormParam = cloneDeep(searchNotPayFormState.value)
		// 获取数据
		const res = await bizSaleProjectApi.bizSaleProjectListDetail({
			projectState: 'PARTIALLY_SHIPPED,WAIT_DELIVER,SHIPPED,COMPLETED',
			playState: 'UNPAID,PARTIALLY_PAID',
			...searchFormParam
		})

		// 创建工作簿和工作表
		const workbook = new ExcelJS.Workbook()
		const worksheet = workbook.addWorksheet('Sheet1')

		// 定义列标题
		const column = ['业务员', '单位名称', '合同金额', '总金额', '回扣金额', '欠款金额', '所属年度', '所属月份']

		let maxLength = column.map((v) => {
			return v.length
		})

		// 添加标题行
		worksheet.addRow(column)

		// 设置标题行样式
		worksheet.getRow(1).eachCell((cell) => {
			cell.font = { bold: true } // 加粗
			cell.alignment = { horizontal: 'center', vertical: 'middle' } // 居中
		})

		// 处理数据并添加到工作表
		res.forEach((record) => {
			let v = record.bizSaleProject

			v.totalPrice = v.totalPrice || 0
			v.rebateAmount = v.rebateAmount || 0
			const arrears = new Decimal(v.totalPrice).sub(new Decimal(v.amountCollected)).toString()
			const date = new Date(v.completionDate)

			// 获取年份和月份
			const year = date.getFullYear()
			const month = date.getMonth() + 1

			const base = [v.headName, v.unit, v.initPrice, v.totalPrice, v.rebateAmount, arrears, year, month]
			base.forEach((v, index) => {
				if (v.length > maxLength[index]) {
					maxLength[index] = v.length
				}
			})

			// 添加数据行
			worksheet.addRow(base)
		})

		// 设置数据行样式
		worksheet.eachRow((row, rowNumber) => {
			if (rowNumber > 1) {
				// 跳过标题行
				row.eachCell((cell) => {
					cell.alignment = { horizontal: 'center', vertical: 'middle' } // 居中
				})
			}
		})

		// 设置列宽
		worksheet.columns = column.map((col, i) => ({
			header: col,
			key: col,
			width: maxLength[i] + 20 // 动态列宽
		}))

		// 设置行高
		worksheet.eachRow((row) => {
			row.height = 20 // 固定行高
		})

		// 生成 Excel 文件
		const buffer = await workbook.xlsx.writeBuffer()
		const file = new Blob([buffer], { type: 'application/octet-stream' })
		saveAs(file, '未回款总计.xlsx')
	})

	const reset = () => {
		searchFormRef.value.resetFields()
	}
	const { treeData, loadingTreeData } = useOrg()

	const onOpen = async (record) => {
		searchFormState.value.projectState = record && record.projectState ? record.projectState : []

		if (record && record.startCompletionTime && record.endCompletionTime) {
			searchFormState.value.completionTime = [record.startCompletionTime, record.endCompletionTime]
		}
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
