<template>
	<xn-form-container title="库存信息导出" width="960px" v-model:open="open" :destroy-on-close="true" @close="onClose">
		<a-space direction="vertical">
			<a-card :bordered="false">
				<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
					<a-row :gutter="24">
						<a-col :span="12">
							<a-form-item label="当前仓库" name="currenWareHouse">
								<a-select
									:fieldNames="{
										label: 'name',
										value: 'id'
									}"
									v-model:value="searchFormState.warehousesId"
									:options="currentWareHouseList"
								></a-select>
							</a-form-item>
						</a-col>
						<a-col :span="12">
							<a-form-item label="产品名称" name="productName">
								<a-input v-model:value="searchFormState.productName" placeholder="请输入产品名称" />
							</a-form-item>
						</a-col>

						<a-col :span="12">
							<a-button :loading="loading" type="primary" @click="exportExcel">导出</a-button>
							<a-button style="margin: 0 8px" @click="reset">重置</a-button>
						</a-col>
					</a-row>
				</a-form>
			</a-card>
			<a-card :bordered="false">
				<a-form
					ref="searchFormOtherRef"
					name="advanced_search"
					:model="searchFormStateOther"
					class="ant-advanced-search-form"
				>
					<a-row :gutter="24">
						<a-col :span="12">
							<a-form-item required label="所属组织：" name="orgId">
								<a-tree-select
									v-model:value="searchFormStateOther.orgId"
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
							<a-form-item required label="所属仓库" name="warehousesId">
								<a-select
									:fieldNames="{
										label: 'name',
										value: 'id'
									}"
									v-model:value="searchFormStateOther.warehousesId"
									:options="currentWareHouseList"
								></a-select>
							</a-form-item>
						</a-col>
						<a-col :span="12">
							<a-form-item label="产品名称" name="productName">
								<a-input v-model:value="searchFormStateOther.productName" placeholder="请输入产品名称" />
							</a-form-item>
						</a-col>
						<a-col :span="12">
							<a-form-item label="出库时间" name="completionTime">
								<a-range-picker
									value-format="YYYY-MM-DD HH:mm:ss"
									v-model:value="searchFormStateOther.completionTime"
									show-time
								/>
							</a-form-item>
						</a-col>

						<a-col :span="12">
							<a-button :loading="otherLoading" type="primary" @click="exportExcelOther">导出差异化出库单</a-button>
							<a-button style="margin: 0 8px" @click="reset">重置</a-button>
						</a-col>
					</a-row>
				</a-form>
			</a-card>
		</a-space>
	</xn-form-container>
</template>
<script setup name="projectExport">
	import { useOrg } from '@/composables/useOrg'
	const open = ref(false)
	import { useLoading } from '@/composables/useLoading'
	import { cloneDeep } from 'lodash-es'
	import inventoryApi from '@/api/biz/inventoryApi'
	import deliveryRecordApi from '@/api/biz/deliveryRecordApi'
	import ExcelJS from 'exceljs'
	import { Decimal } from 'decimal.js'
	import { saveAs } from 'file-saver'

	const currentWareHouseList = ref([])
	const searchFormState = ref({})
	const searchFormStateOther = ref({})
	const searchFormRef = ref()
	const searchFormOtherRef = ref()

	const reset = () => {
		searchFormRef.value.resetFields()
	}
	const { treeData, loadingTreeData } = useOrg()
	const { load: exportExcel, loading } = useLoading(async () => {
		const searchFormParam = cloneDeep(searchFormState.value)

		const res = await inventoryApi.inventoryList(searchFormParam)

		const workbook = new ExcelJS.Workbook()
		const worksheet = workbook.addWorksheet('Sheet1')

		// 定义列标题
		const column = ['产品名称', '当前库存']

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
			const base = [record.productName, record.currentCount]
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
		saveAs(file, '库存.xlsx')
	})

	const { load: exportExcelOther, loading: otherLoading } = useLoading(async () => {
		const searchFormParam = cloneDeep(searchFormStateOther.value)

		try {
			searchFormOtherRef.value.validateFields()

			const res = await deliveryRecordApi.exportOtherCompanyRecordsList(searchFormParam)

			const workbook = new ExcelJS.Workbook()
			const worksheet = workbook.addWorksheet('Sheet1')

			// 定义列标题
			const column = ['产品名称', '出库仓库', '出库时间', '经办人', '备注']

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
				const base = [
					record.productName,
					record.warehousesName,
					record.deliveryTime,
					record.operatorName,
					record.remark
				]
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
			saveAs(file, '差异化出库记录.xlsx')
		} catch (e) {
			return
		}
	})
	const onOpen = async (record) => {
		loadingTreeData()
		searchFormState.value.warehousesId = record && record.id ? record.id : ''
		currentWareHouseList.value = record.currentWareHouseList ? record.currentWareHouseList : []

		open.value = true
	}
	const onClose = () => {
		searchFormRef.value.resetFields()
		searchFormOtherRef.value.resetFields()
		open.value = false
	}
	defineExpose({
		onOpen
	})
</script>

<style scoped></style>
