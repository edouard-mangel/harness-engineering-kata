export class WarehouseDeskApp {
  private stockBySku: Record<string, number> = {};
  private reservedBySku: Record<string, number> = {};
  private priceBySku: Record<string, number> = {};
  private orderStatus: Record<string, string> = {};
  private orderSku: Record<string, string> = {};
  private orderQty: Record<string, number> = {};
  private eventLog: string[] = [];
  private cashBalance = 0;
  private nextOrderNumber = 0;

  seedData(): void {
    this.stockBySku = { 'PEN-BLACK': 40, 'PEN-BLUE': 25, 'NOTE-A5': 15, 'STAPLER': 4 };
    this.reservedBySku = { 'PEN-BLACK': 0, 'PEN-BLUE': 0, 'NOTE-A5': 0, 'STAPLER': 0 };
    this.priceBySku = { 'PEN-BLACK': 1.5, 'PEN-BLUE': 1.6, 'NOTE-A5': 4.0, 'STAPLER': 12.0 };
    this.cashBalance = 300.0;
    this.nextOrderNumber = 1001;
  }

  runDemoDay(): void {
    const commands = [
      'RECV;NOTE-A5;5;2.20',
      'SELL;alice;PEN-BLACK;10',
      'SELL;bob;STAPLER;5',
      'CANCEL;O1002',
      'COUNT;STAPLER',
      'SELL;carol;STAPLER;2',
      'SELL;dan;NOTE-A5;14',
      'COUNT;NOTE-A5',
      'DUMP',
    ];
    for (const command of commands) {
      this.processLine(command);
    }
    this.printEndOfDayReport();
  }

  processLine(line: string): void {
    const parts = line.split(';');
    const type = parts[0];

    if (type === 'RECV') {
      const sku = parts[1];
      const qty = this.parseIntVal(parts[2]);
      const unitCost = this.parseFloatVal(parts[3]);
      this.stockBySku[sku] = (this.stockBySku[sku] ?? 0) + qty;
      this.cashBalance -= qty * unitCost;
      this.eventLog.push(`received ${qty} of ${sku} at ${unitCost}`);
      return;
    }

    if (type === 'SELL') {
      const customer = parts[1];
      const sku = parts[2];
      const qty = this.parseIntVal(parts[3]);
      const orderId = `O${this.nextOrderNumber++}`;
      this.orderSku[orderId] = sku;
      this.orderQty[orderId] = qty;

      const onHand = this.stockBySku[sku] ?? 0;
      const reserved = this.reservedBySku[sku] ?? 0;
      const available = onHand - reserved;
      if (available < qty) {
        this.orderStatus[orderId] = 'BACKORDER';
        this.eventLog.push(`order ${orderId} backordered for ${customer} sku=${sku} qty=${qty}`);
      } else {
        this.stockBySku[sku] = onHand - qty;
        const unitPrice = this.priceBySku[sku] ?? 0;
        const orderTotal = unitPrice * qty;
        this.cashBalance += orderTotal;
        this.orderStatus[orderId] = 'SHIPPED';
        this.eventLog.push(`order ${orderId} shipped to ${customer} amount=${orderTotal}`);
      }
      return;
    }

    if (type === 'CANCEL') {
      const orderId = parts[1];
      const status = this.orderStatus[orderId] ?? null;
      if (status === null) {
        this.eventLog.push(`cannot cancel ${orderId} because it does not exist`);
        return;
      }

      if (status === 'BACKORDER') {
        this.orderStatus[orderId] = 'CANCELLED';
        this.eventLog.push(`cancelled backorder ${orderId}`);
        return;
      }

      if (status === 'SHIPPED') {
        const sku = this.orderSku[orderId];
        const qty = this.orderQty[orderId] ?? 0;
        this.stockBySku[sku] = (this.stockBySku[sku] ?? 0) + qty;
        const unitPrice = this.priceBySku[sku] ?? 0;
        this.cashBalance -= unitPrice * qty;
        this.orderStatus[orderId] = 'CANCELLED_AFTER_SHIP';
        this.eventLog.push(`cancelled shipped order ${orderId} with restock`);
        return;
      }

      this.eventLog.push(`order ${orderId} could not be cancelled from state ${status}`);
      return;
    }

    if (type === 'COUNT') {
      const sku = parts[1];
      const onHand = this.stockBySku[sku] ?? 0;
      const reserved = this.reservedBySku[sku] ?? 0;
      const available = onHand - reserved;
      this.eventLog.push(`count ${sku} onHand=${onHand} reserved=${reserved} available=${available}`);
      return;
    }

    if (type === 'DUMP') {
      console.log('---- dump ----');
      console.log(`stock=${JSON.stringify(this.stockBySku)}`);
      console.log(`reserved=${JSON.stringify(this.reservedBySku)}`);
      console.log(`orders=${JSON.stringify(this.orderStatus)}`);
      console.log(`cashBalance=${this.cashBalance}`);
      return;
    }

    this.eventLog.push(`unknown command: ${line}`);
  }

  private parseIntVal(value: string): number {
    return parseInt(value.trim(), 10);
  }

  private parseFloatVal(value: string): number {
    return parseFloat(value.trim());
  }

  printEndOfDayReport(): void {
    let shipped = 0;
    let backorder = 0;
    let cancelled = 0;
    for (const status of Object.values(this.orderStatus)) {
      if (status === 'SHIPPED') shipped++;
      else if (status === 'BACKORDER') backorder++;
      else if (status.startsWith('CANCELLED')) cancelled++;
    }

    const lowStock = Object.entries(this.stockBySku)
      .filter(([, qty]) => qty < 5)
      .map(([sku]) => sku);

    console.log();
    console.log('==== end of day ====');
    console.log(`orders shipped: ${shipped}`);
    console.log(`orders backordered: ${backorder}`);
    console.log(`orders cancelled: ${cancelled}`);
    console.log(`cash balance: ${this.cashBalance.toFixed(2)}`);
    console.log(`low stock skus: [${lowStock.join(', ')}]`);
    console.log();
    console.log('events:');
    for (const event of this.eventLog) {
      console.log(` - ${event}`);
    }
  }
}
