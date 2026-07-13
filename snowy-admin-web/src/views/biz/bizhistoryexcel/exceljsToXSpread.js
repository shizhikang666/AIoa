import ExcelJS from 'exceljs'

export function parseTableWithMerges(rows, merges) {
	// 解析Excel坐标（如 "A1"）转换为行列索引（0-based）
	function excelCoordToIndices(coord) {
		const match = coord.match(/^([A-Z]+)(\d+)$/)
		if (!match) return null

		const colStr = match[1]
		const row = parseInt(match[2]) - 1 // Excel是1-based，转为0-based

		// 将列字母转换为数字（A=0, B=1, ..., Z=25, AA=26, ...）
		let col = 0
		for (let i = 0; i < colStr.length; i++) {
			col = col * 26 + (colStr.charCodeAt(i) - 65 + 1)
		}
		col -= 1 // 转为0-based

		return { row, col }
	}

	// 解析合并范围（如 "A1:O1"）
	function parseMergeRange(mergeStr) {
		const [startCoord, endCoord] = mergeStr.split(':')
		const start = excelCoordToIndices(startCoord)
		const end = excelCoordToIndices(endCoord)

		if (!start || !end) return null

		return {
			startRow: start.row,
			startCol: start.col,
			endRow: end.row,
			endCol: end.col,
			rowspan: end.row - start.row + 1,
			colspan: end.col - start.col + 1
		}
	}

	// 构建合并信息映射
	const mergeInfo = {
		// 存储所有合并区域
		ranges: [],
		// 快速查找某个单元格是否在合并中
		cellMap: new Map(),
		// 存储每个单元格应该有的 rowspan 和 colspan
		cellProperties: []
	}

	// 初始化单元格属性数组
	for (let i = 0; i < Object.keys(rows).length; i++) {
		mergeInfo.cellProperties[i] = []
		for (let j = 0; j < Object.keys(rows[i].cells).length; j++) {
			mergeInfo.cellProperties[i][j] = { rowspan: 1, colspan: 1, isMerged: false }
		}
	}

	// 解析每个合并范围
	merges.forEach((mergeStr) => {
		const range = parseMergeRange(mergeStr)
		if (range) {
			mergeInfo.ranges.push(range)

			// 标记所有在合并范围内的单元格
			for (let row = range.startRow; row <= range.endRow; row++) {
				for (let col = range.startCol; col <= range.endCol; col++) {
					const key = `${row},${col}`
					mergeInfo.cellMap.set(key, range)

					if (row === range.startRow && col === range.startCol) {
						// 主单元格（合并区域的左上角）
						mergeInfo.cellProperties[row][col] = {
							rowspan: range.rowspan,
							colspan: range.colspan,
							isMerged: true,
							isMergeStart: true
						}
					} else {
						// 被合并的单元格
						mergeInfo.cellProperties[row][col] = {
							rowspan: 0,
							colspan: 0,
							isMerged: true,
							isMergeStart: false
						}
					}
				}
			}
		}
	})

	return {
		tableData: rows,
		mergeInfo: mergeInfo,
		merges: merges,
		/**
		 * 获取单元格的合并属性
		 * @param {number} rowIndex - 行索引（0-based）
		 * @param {number} colIndex - 列索引（0-based）
		 * @returns {Object} - 包含 rowspan 和 colspan 的对象
		 */
		getCellProperties: function (rowIndex, colIndex) {
			if (
				rowIndex >= 0 &&
				rowIndex < this.mergeInfo.cellProperties.length &&
				colIndex >= 0 &&
				colIndex < this.mergeInfo.cellProperties[rowIndex].length
			) {
				return this.mergeInfo.cellProperties[rowIndex][colIndex]
			}
			return { rowspan: 1, colspan: 1, isMerged: false }
		}
	}
}

export async function exceljsToXSpread(arrayBuffer) {
	const workbook = new ExcelJS.Workbook()
	await workbook.xlsx.load(arrayBuffer)

	const result = []

	workbook.eachSheet((worksheet) => {
		// x-spreadsheet 的单个 sheet 基础结构

		const sheetData = {
			name: worksheet.name,
			rows: {},
			merges: [],
			cols: {}
		}

		// 1. 处理合并单元格 (Merges)
		// ExcelJS 的 merges 格式为 "A1:C3"
		if (worksheet.model.merges) {
			sheetData.merges = worksheet.model.merges.map((mergeRange) => mergeRange)
		}

		// 2. 遍历行与单元格
		worksheet.eachRow({ includeEmpty: true }, (row, rowNumber) => {
			// x-spreadsheet 索引从 0 开始

			const rIndex = rowNumber - 1
			sheetData.rows[rIndex] = { cells: {} }

			row.eachCell({ includeEmpty: true }, (cell, colNumber) => {
				const cIndex = colNumber - 1

				// 基础单元格对象
				const cellView = {
					text: cell.value
				}

				// 处理富文本 (RichText) 情况
				if (cell.value && cell.value.richText) {
					cellView.text = cell.value.richText.map((t) => t.text).join('')
				}
				// 处理公式
				if (cell.formula) {
					//	cellView.text
					cellView.text = cell.result !== undefined ? cell.result.toString() : ''
					cellView.formula = cell.formula
				}

				// 3. 基础样式映射 (Style)
				const style = {}
				if (cell.font) {
					if (cell.font.bold) style.bold = true
				}
				if (cell.alignment) {
					if (cell.alignment.horizontal) style.align = cell.alignment.horizontal
					if (cell.alignment.vertical) style.valign = cell.alignment.vertical
				}

				// 如果有样式，则赋值
				if (Object.keys(style).length > 0) {
					cellView.style = style
				}

				sheetData.rows[rIndex].cells[cIndex] = cellView
			})
		})

		// 4. 处理列宽 (可选)
		worksheet.columns.forEach((col, index) => {
			if (col.width) {
				sheetData.cols[index] = { width: col.width * 8 } // 转换比例约等于 8
			}
		})

		result.push(sheetData)
	})

	const res = result.map((v) => {
		const { merges, name, rows } = v

		const obj = parseTableWithMerges(rows, merges)

		const table = []
		Object.keys(rows).forEach((index) => {
			const row = []
			const cells = rows[index].cells
			Object.keys(cells).forEach((cellId) => {
				const cellProps = obj.getCellProperties(index, cellId)
				// 如果这个单元格被合并且不是合并的起始单元格，则不渲染

				if (cellProps.isMerged && !cellProps.isMergeStart) {
					return
				}
				const cell = {}
				if (cells[cellId].text === null || cells[cellId].text === undefined) {
					cells[cellId].text = ''
				}
				cell.text = cells[cellId].text === 0 ? '0' : cells[cellId].text

				if (cells[cellId].formula !== undefined) {
					cell.formula = cells[cellId].formula
				}
				if (cellProps.isMerged) {
					cell.merge = [0, cellProps.colspan - 1]
				}
				row.push(cell)
			})

			table.push(row)
		})
		return {
			table,
			name
		}
	})

	return res
}
