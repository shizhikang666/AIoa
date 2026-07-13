/*! xlsxspread.js (C) SheetJS LLC -- https://sheetjs.com/ */
/* eslint-env browser */
/*global XLSX */
/*exported stox, xtos */
import { read, utils, writeFile } from 'xlsx'

/**
 * Converts data from SheetJS to x-spreadsheet
 *
 * @param  {Object} wb SheetJS workbook object
 *
 * @returns {Object[]} An x-spreadsheet data
 */
export default function stox(wb) {
	const out = []

	wb.SheetNames.forEach((name) => {
		const ws = wb.Sheets[name]
		if (!ws || !ws['!ref']) return

		const o = { name, rows: {}, cols: {} }

		const range = utils.decode_range(ws['!ref'])
		range.s = { r: 0, c: 0 }

		const aoa = utils.sheet_to_json(ws, {
			raw: false,
			header: 1,
			range
		})

		aoa.forEach((r, i) => {
			const cells = {}

			// 行高
			const rowInfo = ws['!rows']?.[i]

			const row = { cells }
			if (rowInfo?.hpx) row.height = rowInfo.hpx

			r.forEach((c, j) => {
				const cellRef = utils.encode_cell({ r: i, c: j })
				const cell = ws[cellRef]

				const xCell = { text: c }

				// 公式
				if (cell?.f) {
					xCell.text = '=' + cell.f
				}

				// 样式（展示向）
				if (cell?.s) {
					const s = {}

					// 对齐
					if (cell.s.alignment) {
						if (cell.s.alignment.horizontal) s.align = cell.s.alignment.horizontal

						if (cell.s.alignment.vertical)
							s.valign = cell.s.alignment.vertical === 'center' ? 'middle' : cell.s.alignment.vertical
					}

					// 字体
					if (cell.s.font) {
						s.font = {
							bold: !!cell.s.font.bold,
							size: cell.s.font.sz || 12
						}
					}

					// 背景色
					const bg = cell.s.fill?.fgColor?.rgb
					if (bg) {
						s.bgcolor = `#${bg.slice(2)}`
					}

					if (Object.keys(s).length) {
						xCell.style = s
					}
				}

				cells[j] = xCell
			})

			o.rows[i] = row
		})

		// 列宽
		;(ws['!cols'] || []).forEach((col, i) => {
			if (col?.wpx) {
				o.cols[i] = { width: col.wpx }
			}
		})

		// 合并单元格
		o.merges = []
		;(ws['!merges'] || []).forEach((merge, i) => {
			if (!o.rows[merge.s.r]) o.rows[merge.s.r] = { cells: {} }
			if (!o.rows[merge.s.r].cells[merge.s.c]) o.rows[merge.s.r].cells[merge.s.c] = {}

			o.rows[merge.s.r].cells[merge.s.c].merge = [merge.e.r - merge.s.r, merge.e.c - merge.s.c]

			o.merges[i] = utils.encode_range(merge)
		})

		out.push(o)
	})

	return out
}

export function toJsonData(data) {
	const res = data.map((table, i) => {
		const rows = Object.keys(table.rows).map((row_index) => {
			const row = table.rows[row_index]
			return Object.keys(row.cells).map((c_index) => {
				const cell = row.cells[c_index]
				return {
					merge: cell.merge
				}
			})
		})

		return {
			name: table.name,
			rows: rows
		}
	})
}
