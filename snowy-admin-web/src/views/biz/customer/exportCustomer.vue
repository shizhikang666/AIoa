<template>
	<xn-form-container title="客户数据导出" width="960px" v-model:open="open" :destroy-on-close="true" @close="onClose">
		<a-space direction="vertical">
			<a-card :bordered="false">
				<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
					<a-row :gutter="24">
						<a-col :span="12">
							<a-form-item label="客户名称" name="projectName">
								<a-input v-model:value="searchFormState.projectName" placeholder="请输入项目名称" />
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
							<a-form-item label="客户负责人" name="user">
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
							<a-button :loading="loading" type="primary" @click="exportExcel">导出</a-button>
							<a-button style="margin: 0 8px" @click="reset">重置</a-button>
						</a-col>
					</a-row>
				</a-form>
			</a-card>
		</a-space>
	</xn-form-container>
</template>
<script setup name="projectExport">
	import { useTemplateRef } from 'vue'
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import customerApi from '@/api/biz/customerApi'
	import { useLoading } from '@/composables/useLoading'
	import { saveAs } from 'file-saver'
	import { Decimal } from 'decimal.js'
	import ExcelJS from 'exceljs'
	import { useOrg } from '@/composables/useOrg'

	const open = ref(false)
	const searchFormState = ref({})
	const searchFormRef = useTemplateRef('searchFormRef')

	// 导出 Excel 文件
	const exportToExcel = async (res) => {
		// 数据处理函数
		const processData = (res) => {
			// 收集所有跟进记录，确定最大列数
			let maxFollowUps = 0

			// 提前遍历计算最大跟进记录数
			res.forEach((item) => {
				const followUps = item.customerFollowUps || []
				if (followUps.length > maxFollowUps) {
					maxFollowUps = followUps.length
				}
			})

			const allData = res.map((item) => {
				const v = item.customer
				let area = v.address ? v.address.split('/') : ['', '', '', '']

				// 处理跟进记录
				const followUps = item.customerFollowUps || []

				// 基础数据
				const baseData = [
					v.createTime,
					v.firstContactTime,
					v.headName,
					v.orgName,
					v.name,
					v.contacts,
					v.phone,
					area[0] || '',
					area[1] || '',
					area[2] || '',
					v.detailsAddress || '',
					tool.dictTypeDataByPath('CUSTOMER', 'CUSTOMER_SOURCE', v.sourceType) || '',
					tool.dictTypeDataByPath('CUSTOMER', 'CUSTOMER_TYPE', v.customType) || '',
					tool.dictTypeDataByPath('COMMON_STATUS', v.status) || '',
					v.dealAmount || 0,
					v.remark || ''
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
							follow.createUserOrgName || '', // 跟进人部门
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
		const worksheet = workbook.addWorksheet('客户数据')

		// 基础列标题
		const baseColumns = [
			'创建日期',
			'首次联系时间',
			'负责人',
			'所属公司',
			'客户名称',
			'联系人',
			'联系电话',
			'省份',
			'城市',
			'区县',
			'详细地址',
			'客户来源',
			'客户类型',
			'状态',
			'成交次数',
			'备注'
		]

		// 处理数据，获取最大跟进记录数
		const processed = processData(res)
		const data = processed.data
		const maxFollowUps = processed.maxFollowUps

		// 动态添加跟进记录列（每个跟进记录4列）
		const followUpColumns = []
		for (let i = 1; i <= maxFollowUps; i++) {
			followUpColumns.push(`跟进${i}-时间`, `跟进${i}-跟进人`, `跟进${i}-跟进部门`, `跟进${i}-内容`)
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
							case 2: // 部门列
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
					case '跟进部门':
						width = 15
						break
					case '内容':
						width = 40
						break
				}
			} else if (col === '客户名称' || col === '详细地址') {
				width = 25
			} else if (col === '备注') {
				width = 30
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

		// 冻结标题行和基础列
		worksheet.views = [
			{
				state: 'frozen',
				xSplit: baseColumns.length, // 冻结基础列，方便查看跟进记录时基础信息固定
				ySplit: 1, // 冻结标题行
				topLeftCell: `${String.fromCharCode(65 + baseColumns.length)}2`, // 从第2行跟进记录区域开始滚动
				activeCell: `${String.fromCharCode(65 + baseColumns.length)}2`
			}
		]

		// 生成 Excel 文件
		const buffer = await workbook.xlsx.writeBuffer()
		const file = new Blob([buffer], { type: 'application/octet-stream' })
		saveAs(file, `客户数据导出_${new Date().getTime()}.xlsx`)
	}

	const { load: exportExcel, loading } = useLoading(async () => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// createTime范围查询条件重载
		if (searchFormParam.createTime) {
			searchFormParam.startCreateTime = searchFormParam.createTime[0]
			searchFormParam.endCreateTime = searchFormParam.createTime[1]
			delete searchFormParam.createTime
		}

		const res = await customerApi.customerDetailList(searchFormParam)
		await exportToExcel(res)
	})

	const reset = () => {
		searchFormRef.value.resetFields()
	}
	const { treeData, loadingTreeData } = useOrg()

	const onOpen = async () => {
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
