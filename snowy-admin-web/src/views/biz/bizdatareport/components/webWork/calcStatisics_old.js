import dayjs from '@/utils/dayjs'
import { Decimal } from 'decimal.js'

self.onmessage = function (event) {
	// 判断 date1 是否在 date2 的月份之前或包括这个月份
	function isBeforeOrSameMonth(date1, date2) {
		// 获取 date1 和 date2 的年份和月份
		const year1 = date1.year()
		const month1 = date1.month()
		const year2 = date2.year()
		const month2 = date2.month()

		// 判断 date1 是否在 date2 的月份之前或包括这个月份
		return year1 < year2 || (year1 === year2 && month1 <= month2)
	}

	// 判断 date1 是否在 date2 的月份之后，但不包括这个月份
	function isAfterMonthButNotSame(date1, date2) {
		// 获取 date1 和 date2 的年份和月份
		const year1 = date1.year()
		const month1 = date1.month()
		const year2 = date2.year()
		const month2 = date2.month()

		// 判断 date1 是否在 date2 的月份之后，但不包括这个月份
		if (year1 > year2 || (year1 === year2 && month1 > month2)) {
			return true
		}
		return false
	}

	let calc = function (result, year) {
		let { bizSaleProjects, paymentRecords, org, bizExpenditureRecords, settlementAccounts, bizDebitNotes } = result
		const month = []
		const projectIdMap = {}
		const queryYear = dayjs(year)
		//是否当前年份
		const isCurren = (date) => {
			if ((!date) instanceof dayjs) {
				date = dayjs(date)
			}
			return queryYear.isSame(date, 'year')
		}
		//是否之前年份
		const isPre = (date) => {
			// 如果 date 不是 dayjs 对象，则将其转换为 dayjs 对象
			if (!dayjs.isDayjs(date)) {
				date = dayjs(date)
			}

			// 检查 date 是否在 queryYear 之前
			return date.isBefore(queryYear, 'year')
		}

		//之前的年份的营业额度
		let preYearAmount = new Decimal(0)
		//之前年份的账户余额
		let preYearAccountAmount = new Decimal(0)
		let preArrears = new Decimal(0)
		//所有的未回款的项目
		const unPayProject = []
		//所有的未回款的个人借条
		const unPayLoan = []

		//每个月成交的项目，及其对应的收款金额
		const monthProject = {}

		//每个月收回的未回款 及其对应的项目
		const monthOldPayProject = []

		//生成12个月份数组
		for (let i = 0; i < 12; i++) {
			monthOldPayProject[i] = []
			month[i] = {
				//每个月的业绩总额
				totalAmount: new Decimal(0), //业绩总额
				//其他营收总额
				otherTotalAmount: new Decimal(0),
				//本月新增未回款
				unPaidPaymentAmount: new Decimal(0),
				//本月收回未回款
				recoveredUnpaidAmounts: new Decimal(0),
				//本月项目本月收款总额
				payAmount: new Decimal(0),
				//本月总未收款
				totalUnpaidAmount: new Decimal(0),
				//本月总开支,不包括账号互转
				totalExpenditure: new Decimal(0),
				//本月总收入，不包括账号互转,包括了账户创建的时候的金额
				totalPaymentAmount: new Decimal(0),
				//每个月的剩余额
				balance: new Decimal(0),
				//每个月的个人借款代收款的还款余额
				loanRepayment: new Decimal(0),
				//每个月的个人借款金额
				loan: new Decimal(0)
			}
		}

		bizSaleProjects.forEach((project) => {
			projectIdMap[project.id] = project
			const completionDate = dayjs(project.completionDate)
			//如果是指定年份的则记录成交额
			if (isCurren(completionDate)) {
				const index = completionDate.month()
				const activeMoth = month[index]
				activeMoth.totalAmount = month[index].totalAmount.add(project.totalPrice)
				//历史已收款记录本月项目本月收款总额 减去本月项目退款总额
				activeMoth.payAmount = month[index].payAmount.add(project.historyAmount).sub(project.totalReturnAmount)

				if (!monthProject[index]) {
					monthProject[index] = {}
				}
				//归类每个月份的成交的项目
				monthProject[index][project.id] = {
					project,
					paymentRecords: []
				}
			} else {
				preYearAmount = preYearAmount.add(new Decimal(project.totalPrice).sub(project.historyAmount))
			}

			if (project.playState !== 'PAID') {
				const unPayAmount = new Decimal(project.totalPrice).sub(project.amountCollected).toNumber()
				unPayProject.push({
					...project,
					company: org.name,
					unPayAmount,
					year: completionDate.year(),
					month: completionDate.month() + 1
				})
			}
		})
		paymentRecords.forEach((paymentRecord) => {
			const payerTime = dayjs(paymentRecord.payerTime)
			//如果是本年的收款
			if (isCurren(payerTime)) {
				const index = payerTime.month()
				const activeMoth = month[index]

				//不是往来计入当前月份的总收入
				if (paymentRecord.settlementCategory !== 'dealings') {
					activeMoth.totalPaymentAmount = activeMoth.totalPaymentAmount.add(paymentRecord.amount)
				}

				//如果是借款还款
				if (paymentRecord.settlementCategory === 'LoanRepayment') {
					activeMoth.loanRepayment = activeMoth.loanRepayment.add(paymentRecord.amount)
				}

				//如果是项目收款
				if (paymentRecord.settlementCategory === 'PROJECT_PLAY') {
					//如果项目存在
					if (projectIdMap[paymentRecord.objectId]) {
						let completionDate = dayjs(projectIdMap[paymentRecord.objectId].completionDate)
						let completionMonth = month[completionDate.month()]
						//如果收款时间在项目成交时间之前或者项目成交这个月份之内的
						if (isBeforeOrSameMonth(payerTime, completionDate)) {
							//如果是本月的项目则添加为本月项目本月收款总额
							completionMonth.payAmount = completionMonth.payAmount.add(paymentRecord.amount)
							//给每个月的项目添加 每月收款
							monthProject[completionDate.month()][paymentRecord.objectId].paymentRecords.push(paymentRecord)
							//如果收款日期在项目成交日期那个月之后的
						} else if (isAfterMonthButNotSame(payerTime, completionDate)) {
							activeMoth.recoveredUnpaidAmounts = activeMoth.recoveredUnpaidAmounts.add(paymentRecord.amount)
							let { projectName, id } = projectIdMap[paymentRecord.objectId]
							monthOldPayProject[payerTime.month()].push({
								...paymentRecord,
								projectName,
								projectId: id
							})
						}
					}
				} else if (
					paymentRecord.settlementCategory !== 'LoanRepayment' &&
					paymentRecord.settlementCategory !== 'dealings' &&
					paymentRecord.settlementCategory !== 'Collection'
				) {
					activeMoth.otherTotalAmount = activeMoth.otherTotalAmount.add(paymentRecord.amount)
				}
			} else {
				//如果是借款还款
				if (paymentRecord.settlementCategory === 'LoanRepayment') {
					preArrears = preArrears.sub(paymentRecord.amount)
				}

				if (paymentRecord.settlementCategory === 'PROJECT_PLAY') {
					//如果不是本年的收款记录，则减少总的未收款鹅度
					preYearAmount = preYearAmount.sub(paymentRecord.amount)
				}

				//不是往来计入当前之前总收入
				if (paymentRecord.settlementCategory !== 'dealings') {
					preYearAccountAmount = preYearAccountAmount.add(paymentRecord.amount)
				}
			}
		})
		bizExpenditureRecords.forEach((record) => {
			const payerTime = dayjs(record.payerTime)
			//如果是本年的的支出，则归类支出总计
			if (isCurren(payerTime)) {
				const index = payerTime.month()
				const activeMoth = month[index]
				if (record.settlementCategory !== 'dealings') {
					activeMoth.totalExpenditure = activeMoth.totalExpenditure.add(record.amount)
				}
			} else {
				//不是往来的话减少之前总收入
				if (record.settlementCategory !== 'dealings') {
					preYearAccountAmount = preYearAccountAmount.sub(record.amount)
				}
			}
		})
		settlementAccounts.forEach((account) => {
			const createTime = dayjs(account.createTime)
			if (isCurren(createTime)) {
				const index = createTime.month()
				const activeMoth = month[index]
				activeMoth.totalPaymentAmount = activeMoth.totalPaymentAmount.add(account.initialAmount)
			} else if (isPre(createTime)) {
				preYearAccountAmount = preYearAccountAmount.add(account.initialAmount)
			}
		})
		bizDebitNotes.forEach((debitNote) => {
			const payerTime = dayjs(debitNote.createTime)
			if (isCurren(payerTime)) {
				const index = payerTime.month()
				const activeMoth = month[index]
				activeMoth.loan = activeMoth.loan.add(debitNote.amount).sub(debitNote.historyAmount)
			} else {
				preArrears = preArrears.add(debitNote.amount).sub(debitNote.historyAmount)
			}

			if (debitNote.playStatus !== 'AlreadySettled') {
				const unPayAmount = new Decimal(debitNote.amount).sub(debitNote.settlementAmount).toNumber()
				unPayLoan.push({
					...debitNote,
					company: org.name,
					unPayAmount,
					year: payerTime.year(),
					month: payerTime.month() + 1
				})
			}
		})
		month.forEach((month, index) => {
			//本月新增未回款等于本月总营业额减去本月项目本月收款总额
			month.unPaidPaymentAmount = month.totalAmount.sub(month.payAmount)
			//在这个月的未回款等于上个月（年度）总未回款减去本月回款额度再添加本月新增的未回款
			month.totalUnpaidAmount = preYearAmount.sub(month.recoveredUnpaidAmounts).add(month.unPaidPaymentAmount)
			//账户余额等于去年的账户余额减去每个月的开支加上每个月的收入
			month.balance = preYearAccountAmount.add(month.totalPaymentAmount.sub(month.totalExpenditure))
			//借款
			month.loan = preArrears.add(month.loan.sub(month.loanRepayment))

			//本月销售项目本月收款
			month.projectList = monthProject[index] ? Object.values(monthProject[index]) : []

			//本月收回未回款记录
			month.monthOldPayProject = monthOldPayProject[index] ? monthOldPayProject[index] : []

			preArrears = month.loan
			preYearAccountAmount = month.balance
			preYearAmount = month.totalUnpaidAmount
			Object.keys(month).forEach((key) => {
				if (month[key] instanceof Decimal) {
					month[key] = month[key].toString()
				}
			})
		})
		return {
			org,
			month: month,
			unPayProject,
			unPayLoan
		}
	}
	let { result, year } = event.data
	self.postMessage(calc(result, year))
}
