<template>
	<div class="table-wrapper" ref="tableWrapperRef">
		<a-table
			:scroll="{ x: 5000, y: y }"
			:data-source="formattedDataSource"
			:columns="processedColumns"
			:pagination="false"
			:row-class-name="(_record, index) => (index % 2 === 1 ? 'table-striped' : 'table-striped')"
			bordered
			size="middle"
		>
			<!-- 自定义单元格内容，支持换行 -->
			<template #bodyCell="{ column, text }">
				<div v-html="formatCellContent(text)"></div>
			</template>
		</a-table>
	</div>
</template>

<script setup>
	import { computed } from 'vue'
	import { cloneDeep } from 'lodash-es'

	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	const tableWrapperRef = ref({})
	const y = ref(800)
	onMounted(() => {
		if (tableWrapperRef.value.$el) {
			const cardElement = tableWrapperRef.value.$el

			y.value = cardElement.offsetHeight
		}
	})

	const props = defineProps({
		tableData: {
			type: Array,
			default: () => []
		},
		mergeInfo: {
			type: Object,
			default: () => ({
				cellProperties: []
			})
		}
	})

	// 格式化单元格内容（处理换行和HTML转义）
	const formatCellContent = (text) => {
		if (text === null || text === undefined) return ''
		return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')
	}

	// 处理列配置，支持合并单元格
	const processedColumns = computed(() => {
		const columns = []

		// Excel 列名转换函数：数字索引转 A,B,...Z,AA,AB...
		const getExcelColumnName = (colIndex) => {
			let result = ''
			let index = colIndex + 1 // 从1开始

			while (index > 0) {
				index-- // 因为A对应0，所以先减1
				const remainder = index % 26
				result = String.fromCharCode(65 + remainder) + result // 65是'A'的ASCII码
				index = Math.floor(index / 26)
			}
			return result + '1' // 添加行号1，如 A1, B1 等
		}
		// 根据你的数据结构生成列配置
		if (props.tableData.length > 0) {
			const firstRowLength = props.tableData[0].length
			for (let colIndex = 0; colIndex < firstRowLength; colIndex++) {
				columns.push({
					title: getExcelColumnName(colIndex),
					dataIndex: colIndex.toString(),
					key: `${colIndex}`,
					customCell: (record, rowIndex) => {
						// 获取单元格属性
						const cellProps = getCellProperties(rowIndex, colIndex)

						// 如果这个单元格被合并且不是合并的起始单元格，则不渲染
						if (cellProps.isMerged && !cellProps.isMergeStart) {
							return {
								style: { display: 'none' },
								rowSpan: 0,
								colSpan: 0
							}
						}

						// 返回合并属性
						return {
							rowSpan: cellProps.rowspan > 1 ? cellProps.rowspan : 1,
							colSpan: cellProps.colspan > 1 ? cellProps.colspan : 1
						}
					}
				})
			}
		}

		return columns
	})

	// 将数据转换为Ant Table需要的格式
	const formattedDataSource = computed(() => {
		return props.tableData.map((row, rowIndex) => {
			const record = { key: rowIndex }
			row.forEach((cell, colIndex) => {
				record[colIndex.toString()] = cell
			})
			return record
		})
	})

	// 获取单元格属性
	const getCellProperties = (rowIndex, colIndex) => {
		// 确保 mergeInfo 存在且格式正确
		if (!props.mergeInfo || !props.mergeInfo.cellProperties) {
			return { rowspan: 1, colspan: 1, isMerged: false, isMergeStart: false }
		}

		const cellProperties = props.mergeInfo.cellProperties

		if (
			rowIndex >= 0 &&
			rowIndex < cellProperties.length &&
			colIndex >= 0 &&
			colIndex < cellProperties[rowIndex].length
		) {
			return cellProperties[rowIndex][colIndex]
		}

		return { rowspan: 1, colspan: 1, isMerged: false, isMergeStart: false }
	}
	onMounted(() => {
		console.log(formattedDataSource.value)
		console.log(processedColumns.value)
	})
</script>
<style scoped>
	.table-wrapper {
		height: 100%;
	}

	[data-doc-theme='light'] .ant-table-striped :deep(.table-striped) td {
		background-color: #fafafa;
	}
</style>
