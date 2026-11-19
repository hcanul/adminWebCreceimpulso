import { defineStore } from 'pinia';
import { ref } from 'vue';
import { receiptPrintFormats, type ReceiptPrintFormat } from '../services/receipt-print.service';
const key = 'crece.receipts.print.format.v1';
export const useReceiptPrintStore = defineStore('receiptPrintSettings', () => {
  let saved: string | null = null;
  try {
    saved = localStorage.getItem(key);
  } catch {
    /* Printing works without browser storage. */
  }
  const format = ref<ReceiptPrintFormat>(
    receiptPrintFormats.some((item) => item.value === saved)
      ? (saved as ReceiptPrintFormat)
      : 'letter',
  );
  function setFormat(value: ReceiptPrintFormat) {
    if (!receiptPrintFormats.some((item) => item.value === value)) return;
    format.value = value;
    try {
      localStorage.setItem(key, value);
    } catch {
      /* Keep the current session setting. */
    }
  }
  return { format, setFormat };
});
