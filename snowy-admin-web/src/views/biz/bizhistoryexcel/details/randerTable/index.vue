<template>
	<div class="table-wrapper" ref="tableWrapperRef">
		<div id="xss-demo"></div>
	</div>
</template>

<script setup>
	import Spreadsheet from 'x-data-spreadsheet'
	import 'x-data-spreadsheet/dist/locale/zh-cn'
	import { parseTableWithMerges } from '@/views/biz/bizhistoryexcel/exceljsToXSpread'

	// 更简洁的版本（如果需要自定义格式）
	function formatDateOrOriginal(str) {
		const isoDateRegex = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{3})?Z$/

		if (isoDateRegex.test(str)) {
			const date = new Date(str)
			if (!isNaN(date.getTime())) {
				return date.toLocaleString('zh-CN')
			}
		}

		return str
	}

	function getColumnLetter(columnIndex) {
		let letter = ''
		while (columnIndex >= 0) {
			letter = String.fromCharCode((columnIndex % 26) + 65) + letter
			columnIndex = Math.floor(columnIndex / 26) - 1
		}
		return letter
	}

	function convertMergeArrayToRange(mergeArray, row = 1, col = 0) {
		const [startCol, endCol] = mergeArray
		// 起始列字母（0-based）
		const startLetter = getColumnLetter(startCol + col)
		// 结束列字母（0-based）
		const endLetter = getColumnLetter(endCol + col)

		return `${startLetter}${row}:${endLetter}${row}`
	}

	const tableWrapperRef = ref({})
	Spreadsheet.locale('zh-cn')
	// 获取单元格属性

	let spreadsheet = {}
	const props = defineProps({
		data: {
			type: Array,
			default: () => []
		}
	})

	onMounted(async () => {
		const allData = props.data.map((table) => {
			const rows = {}
			const merges = []
			table.table.map((v, rowIndex) => {
				const cells = {}
				let startCol = 0
				v.map((item, colIndex) => {
					let a = startCol
					if (item.merge) {
						const str = convertMergeArrayToRange(item.merge, rowIndex + 1, startCol)
						merges.push(str)
						startCol += item.merge[1] + 1
						if (item.merge[1] > 10) {
							item.style = 1
						}
					} else {
						startCol++
					}

					if (item.formula) {
						item.text = '=' + item.formula
					} else {
						item.text = formatDateOrOriginal(item.text)
					}

					cells[a] = item
				})
				rows[rowIndex] = {
					cells: cells,
					height: 35
				}
			})
			return {
				rows,
				merges,
				name: table.name,
				styles: [
					{
						align: 'center'
					},
					{
						align: 'center'
						// border: {
						// 	top: ['thin', '#000'],
						// 	bottom: ['thin', '#000'],
						// 	left: ['thin', '#000'],
						// 	right: ['thin', '#000']
						// }
					}
				]
			}
		})

		await nextTick()
		await nextTick()
		await nextTick()
		const width = tableWrapperRef.value.clientWidth
		const height = tableWrapperRef.value.clientHeight

		spreadsheet = new Spreadsheet(document.getElementById('xss-demo'), {
			view: {
				height: () => height,
				width: () => width
			}
		})
			.loadData(allData) // load data
			.change((data) => {})

		window.spreadsheet = spreadsheet
	})

	const getData = () => {
		return spreadsheet.getData()
	}

	defineExpose({
		getData
	})
</script>
<style scoped>
	.table-wrapper {
		width: 100%;
		height: 100%;
	}
</style>
