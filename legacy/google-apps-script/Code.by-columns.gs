/**
 * Google Apps Script v2 — колонки по заголовкам.
 * Поддерживает action: application | payment
 *
 * Заголовки (регистр не важен). Рекомендуемый порядок колонок заявки:
 * name, phone, email, adultsCount, childrenCount, totalAmount, payNowAmount,
 * pricingPeriodName, applicationUuid, productName, participationOptionName,
 * transferIncluded, paymentFactor, notes,
 * payment1Amount, payment1Date, payment1Id,
 * payment2Amount, payment2Date, payment2Id,
 * paidTotal, remaining
 *
 * Запись в ячейки — по имени заголовка, не по индексу.
 */

var SHEET_ID = '1r2LoY04p4pCoknF7s14VkGBnTz-IxHaIkG8Un3W1bA0';
var SHEET_NAME = 'Регистрации';

function doPost(e) {
  try {
    if (!e || !e.postData || !e.postData.contents) {
      return jsonResponse({ status: 'error', message: 'No post data' });
    }

    var data = JSON.parse(e.postData.contents);
    var action = (data.action || 'payment').toString();

    if (action === 'application') {
      return handleApplication(data);
    }

    return handlePayment(data);
  } catch (err) {
    return jsonResponse({ status: 'error', message: err.message });
  }
}

function handleApplication(data) {
  var sheet = getSheet();
  var col = buildColumnMap(sheet);
  var appUuid = (data.applicationUuid || '').toString().trim();

  if (appUuid) {
    var existing = findRowByUuid(sheet, col, appUuid);
    if (existing) {
      return jsonResponse({ status: 'ok', row: existing, message: 'already exists' });
    }
  }

  var row = sheet.getLastRow() + 1;
  setCell(sheet, row, col, 'applicationuuid', appUuid);
  setCell(sheet, row, col, 'name', data.name || '');
  setCell(sheet, row, col, 'email', data.email || '');
  setCell(sheet, row, col, 'phone', data.phone || '');
  setCell(sheet, row, col, 'productname', data.productName || '');
  setCell(sheet, row, col, 'participationoptionname', data.participationOptionName || '');
  setCell(sheet, row, col, 'pricingperiodname', data.pricingPeriodName || '');
  setCell(sheet, row, col, 'adultscount', data.adultsCount || '');
  setCell(sheet, row, col, 'childrencount', data.childrenCount || '');
  setCell(sheet, row, col, 'totalamount', (data.totalAmount || '') + ' RUB');
  setCell(sheet, row, col, 'paynowamount', data.payNowAmount || '');
  setCell(sheet, row, col, 'transferincluded', data.transferIncluded || '0');
  setCell(sheet, row, col, 'paymentfactor', data.paymentFactor || '1');
  setCell(sheet, row, col, 'notes', data.notes || '');
  setCell(sheet, row, col, 'paidtotal', '0.00 RUB');
  setCell(sheet, row, col, 'remaining', (data.totalAmount || '0') + ' RUB');

  return jsonResponse({ status: 'ok', row: row, action: 'application' });
}

function handlePayment(data) {
  var sheet = getSheet();
  var col = buildColumnMap(sheet);
  var lastRow = sheet.getLastRow();

  if (lastRow < 2) {
    return jsonResponse({ status: 'error', message: 'No rows' });
  }

  var email = (data.email || '').toString().trim();
  var phoneNorm = normalizePhoneDigits(data.phone || '');
  var paymentId = (data.paymentId || '').toString().trim();
  var appUuid = (data.applicationUuid || '').toString().trim();
  var amountFloat = parseFloat(data.amount);

  var values = sheet.getRange(2, 1, lastRow - 1, sheet.getLastColumn()).getValues();
  var targetRow = findTargetRow(values, col, email, phoneNorm, paymentId, appUuid, amountFloat);

  if (!targetRow) {
    return jsonResponse({ status: 'error', message: 'User not found' });
  }

  var rowData = values[targetRow - 2];
  var payment1Id = getCell(rowData, col, 'payment1id');
  var payment2Id = getCell(rowData, col, 'payment2id');
  var formattedDate = formatPaidAt(data.paidAt);
  var amountText = data.amount + ' ' + (data.currency || 'RUB');

  if (paymentId === payment1Id) {
    setCell(sheet, targetRow, col, 'payment1amount', amountText);
    if (formattedDate) setCell(sheet, targetRow, col, 'payment1date', formattedDate);
  } else if (paymentId === payment2Id) {
    setCell(sheet, targetRow, col, 'payment2amount', amountText);
    if (formattedDate) setCell(sheet, targetRow, col, 'payment2date', formattedDate);
  } else if (!payment1Id) {
    setCell(sheet, targetRow, col, 'payment1amount', amountText);
    if (formattedDate) setCell(sheet, targetRow, col, 'payment1date', formattedDate);
    setCell(sheet, targetRow, col, 'payment1id', paymentId);
  } else if (!payment2Id) {
    setCell(sheet, targetRow, col, 'payment2amount', amountText);
    if (formattedDate) setCell(sheet, targetRow, col, 'payment2date', formattedDate);
    setCell(sheet, targetRow, col, 'payment2id', paymentId);
  }

  if (data.paidTotal) setCell(sheet, targetRow, col, 'paidtotal', data.paidTotal + ' RUB');
  if (data.remaining) setCell(sheet, targetRow, col, 'remaining', data.remaining + ' RUB');

  return jsonResponse({ status: 'ok', row: targetRow, action: 'payment' });
}

function getSheet() {
  var ss = SpreadsheetApp.openById(SHEET_ID);
  var sheet = ss.getSheetByName(SHEET_NAME);
  if (!sheet) throw new Error('Sheet not found');
  return sheet;
}

function findRowByUuid(sheet, col, appUuid) {
  var lastRow = sheet.getLastRow();
  if (lastRow < 2) return null;
  var values = sheet.getRange(2, 1, lastRow - 1, sheet.getLastColumn()).getValues();
  for (var i = 0; i < values.length; i++) {
    if (getCell(values[i], col, 'applicationuuid') === appUuid) return i + 2;
  }
  return null;
}

function buildColumnMap(sheet) {
  var headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  var map = {};
  for (var i = 0; i < headers.length; i++) {
    var key = String(headers[i] || '').trim().toLowerCase().replace(/\s+/g, '');
    if (key) map[key] = i;
  }
  return map;
}

function findTargetRow(values, col, email, phoneNorm, paymentId, appUuid, amountFloat) {
  var candidates = [];

  for (var i = 0; i < values.length; i++) {
    var row = values[i];
    var rowEmail = getCell(row, col, 'email');
    var rowPhone = normalizePhoneDigits(getCell(row, col, 'phone'));
    var rowUuid = getCell(row, col, 'applicationuuid');

    if (paymentId) {
      var p1 = getCell(row, col, 'payment1id');
      var p2 = getCell(row, col, 'payment2id');
      if (p1 === paymentId || p2 === paymentId) return i + 2;
    }

    if (appUuid && rowUuid === appUuid) return i + 2;

    if (email && phoneNorm && rowEmail === email && rowPhone === phoneNorm) {
      candidates.push({ row: i + 2, total: parseMoney(getCell(row, col, 'totalamount')) });
    }
  }

  if (candidates.length === 0) return null;
  if (!isNaN(amountFloat)) {
    for (var j = 0; j < candidates.length; j++) {
      if (candidates[j].total === amountFloat) return candidates[j].row;
    }
  }
  return candidates[candidates.length - 1].row;
}

function getCell(row, col, name) {
  var idx = col[name.toLowerCase().replace(/\s+/g, '')];
  if (idx === undefined) return '';
  return (row[idx] || '').toString().trim();
}

function setCell(sheet, row, col, name, value) {
  var idx = col[name.toLowerCase().replace(/\s+/g, '')];
  if (idx !== undefined) sheet.getRange(row, idx + 1).setValue(value);
}

function parseMoney(s) {
  return parseFloat(String(s).replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
}

function formatPaidAt(paidAt) {
  if (!paidAt) return '';
  var d = new Date(paidAt);
  if (isNaN(d.getTime())) return '';
  return Utilities.formatDate(d, Session.getScriptTimeZone(), 'dd.MM.yyyy HH:mm:ss');
}

function jsonResponse(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}

function normalizePhoneDigits(s) {
  if (!s) return '';
  var digits = String(s).replace(/\D/g, '');
  if (digits.length === 11 && digits.charAt(0) === '8') digits = '7' + digits.slice(1);
  if (digits.length === 10) digits = '7' + digits;
  return digits;
}
