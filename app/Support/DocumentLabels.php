<?php

namespace App\Support;

use App\Services\DocumentService;

/**
 * Bilingual labels for printable documents.
 *
 * The shop interface is Bangla first, so every document label carries a Bangla
 * string plus a smaller English gloss, exactly like the printed cash memo the
 * counter staff are used to. There are no PHP language files in this project,
 * so the labels are resolved here and passed into the views by
 * {@see DocumentService}.
 */
final class DocumentLabels
{
    /**
     * @return array<string, array{bn: string, en: string}>
     */
    public static function all(): array
    {
        return [
            'saleMemo' => ['bn' => 'বিক্রয় মেমো', 'en' => 'Sale Memo'],
            'paymentReceipt' => ['bn' => 'পেমেন্ট রসিদ', 'en' => 'Payment Receipt'],
            'duplicate' => ['bn' => 'ডুপ্লিকেট', 'en' => 'Duplicate'],
            'serial' => ['bn' => 'ক্রঃ নং', 'en' => 'SL'],
            'description' => ['bn' => 'বিবরণ', 'en' => 'Description'],
            'tag' => ['bn' => 'ট্যাগ', 'en' => 'Tag'],
            'karat' => ['bn' => 'ক্যারেট', 'en' => 'Karat'],
            'weight' => ['bn' => 'ওজন', 'en' => 'Weight'],
            'ratePerGram' => ['bn' => 'প্রতি গ্রামের দর', 'en' => 'Rate / gram'],
            'goldValue' => ['bn' => 'স্বর্ণের মূল্য', 'en' => 'Gold Value'],
            'making' => ['bn' => 'মজুরি', 'en' => 'Making Charge'],
            'stone' => ['bn' => 'পাথর', 'en' => 'Stone'],
            'lineTotal' => ['bn' => 'মোট', 'en' => 'Total'],
            'subtotal' => ['bn' => 'সাবটোটাল', 'en' => 'Subtotal'],
            'discount' => ['bn' => 'ছাড়', 'en' => 'Discount'],
            'vat' => ['bn' => 'ভ্যাট', 'en' => 'VAT'],
            'exchange' => ['bn' => 'পুরোনো সোনা বিনিময়', 'en' => 'Old Gold Exchange'],
            'exchangeDetail' => ['bn' => 'বিনিময়ের বিবরণ', 'en' => 'Exchange Detail'],
            'grandTotal' => ['bn' => 'মোট মূল্য', 'en' => 'Grand Total'],
            'paid' => ['bn' => 'অগ্রীম প্রদত্ত', 'en' => 'Advance Paid'],
            'due' => ['bn' => 'অবশিষ্ট টাকা', 'en' => 'Balance Due'],
            'payments' => ['bn' => 'পেমেন্ট', 'en' => 'Payments'],
            'method' => ['bn' => 'মাধ্যম', 'en' => 'Method'],
            'reference' => ['bn' => 'রেফারেন্স', 'en' => 'Reference'],
            'receivedBy' => ['bn' => 'গ্রহণকারী', 'en' => 'Received By'],
            'customer' => ['bn' => 'ক্রেতা', 'en' => 'Customer'],
            'phone' => ['bn' => 'মোবাইল', 'en' => 'Phone'],
            'address' => ['bn' => 'ঠিকানা', 'en' => 'Address'],
            'customerCode' => ['bn' => 'ক্রেতা কোড', 'en' => 'Customer Code'],
            'invoiceNo' => ['bn' => 'ইনভয়েস নং', 'en' => 'Invoice No'],
            'receiptNo' => ['bn' => 'রসিদ নং', 'en' => 'Receipt No'],
            'date' => ['bn' => 'তারিখ', 'en' => 'Date'],
            'soldBy' => ['bn' => 'বিক্রেতা', 'en' => 'Sold By'],
            'walkIn' => ['bn' => 'হাঁদাবাজার ক্রেতা', 'en' => 'Walk-in Customer'],
            'paidInFull' => ['bn' => 'সম্পূর্ণ পরিশোধিত', 'en' => 'Paid in Full'],
            'voided' => ['bn' => 'বাতিলকৃত বিক্রয়', 'en' => 'VOID SALE'],
            'voidReason' => ['bn' => 'বাতিলের কারণ', 'en' => 'Void Reason'],
            'customerSign' => ['bn' => 'ক্রেতার স্বাক্ষর', 'en' => 'Customer Signature'],
            'sellerSign' => ['bn' => 'বিক্রেতার স্বাক্ষর', 'en' => 'Seller Signature'],
            'cashierSign' => ['bn' => 'ক্যাশিয়ারের স্বাক্ষর', 'en' => 'Cashier Signature'],
            'thanks' => ['bn' => 'ধন্যবাদ আবার আসবেন', 'en' => 'Thank You, Visit Again'],
            'itemsHeading' => ['bn' => 'বিক্রীত পণ্যের তালিকা', 'en' => 'Sold Items'],
            'exchangeHeading' => ['bn' => 'জমা দেওয়া পুরোনো সোনা', 'en' => 'Old Gold Received'],
            'paymentsHeading' => ['bn' => 'প্রদত্ত পেমেন্ট', 'en' => 'Payments Received'],
            'amountInWords' => ['bn' => 'কথায়', 'en' => 'In Words'],
            'gram' => ['bn' => 'গ্রাম', 'en' => 'g'],
            'vori' => ['bn' => 'ভরি', 'en' => 'vori'],
            'handwrittenTag' => ['bn' => 'হাতে লেখা পণ্য', 'en' => 'Hand written'],
            'printNote' => ['bn' => 'কম্পিউটারে তৈরি, স্বাক্ষরের প্রয়োজনে উপস্থিত থাকুন।', 'en' => 'Computer generated memo.'],
            'pawnTicket' => ['bn' => 'বন্ধকর টিকিট', 'en' => 'Pawn Ticket'],
            'pawnPaymentReceipt' => ['bn' => 'বন্ধক পেমেন্ট রসিদ', 'en' => 'Pawn Payment Receipt'],
            'redemptionReceipt' => ['bn' => 'বন্ধক ফেরত রসিদ', 'en' => 'Redemption Receipt'],
            'pawnNo' => ['bn' => 'বন্ধক নং', 'en' => 'Pawn No'],
            'principal' => ['bn' => 'মূলধন', 'en' => 'Principal'],
            'interestRate' => ['bn' => 'সুদের হার', 'en' => 'Interest Rate'],
            'interestType' => ['bn' => 'সুদের ধরন', 'en' => 'Interest Type'],
            'simpleInterest' => ['bn' => 'সরল সুদ', 'en' => 'Simple Interest'],
            'termStart' => ['bn' => 'মেয়াদ শুরু', 'en' => 'Term Start'],
            'dueDate' => ['bn' => 'পরিশোধের তারিখ', 'en' => 'Due Date'],
            'pledgedItems' => ['bn' => 'বন্ধক দেওয়া পণ্যের তালিকা', 'en' => 'Pledged Items'],
            'estimatedValue' => ['bn' => 'আনুমানিক মূল্য', 'en' => 'Estimated Value'],
            'itemPhoto' => ['bn' => 'ছবি', 'en' => 'Photo'],
            'ledger' => ['bn' => 'হিসাবের খতিয়ান', 'en' => 'Payment Ledger'],
            'interestAccrued' => ['bn' => 'অর্জিত সুদ', 'en' => 'Interest Accrued'],
            'interestPaid' => ['bn' => 'পরিশোধিত সুদ', 'en' => 'Interest Paid'],
            'interestDue' => ['bn' => 'বকেয়া সুদ', 'en' => 'Interest Due'],
            'outstandingPrincipal' => ['bn' => 'অবশিষ্ট মূলধন', 'en' => 'Outstanding Principal'],
            'totalPayable' => ['bn' => 'মোট পরিশোধযোগ্য', 'en' => 'Total Payable'],
            'principalPaid' => ['bn' => 'পরিশোধিত মূলধন', 'en' => 'Principal Repaid'],
            'interestOnly' => ['bn' => 'শুধু সুদ', 'en' => 'Interest'],
            'principalOnly' => ['bn' => 'শুধু মূলধন', 'en' => 'Principal'],
            'redemption' => ['bn' => 'সম্পূর্ণ পরিশোধ', 'en' => 'Redemption'],
            'renewal' => ['bn' => 'বন্ধক নবায়ন', 'en' => 'Renewal'],
            'forfeited' => ['bn' => 'বন্ধক বাতিল', 'en' => 'FORFEITED PAWN'],
            'termsHeading' => ['bn' => 'শর্তাবলি', 'en' => 'Terms and Conditions'],
            'loanToValue' => ['bn' => 'ঋণের হার', 'en' => 'Loan to Value'],
            'pledgedOn' => ['bn' => 'বন্ধকর তারিখ', 'en' => 'Pawned On'],
            'releasedOn' => ['bn' => 'ফেরতের তারিখ', 'en' => 'Released On'],
            'goodsReturned' => ['bn' => 'পণ্য ফেরত দেওয়া হয়েছে', 'en' => 'Goods returned to the customer'],
            'printA4' => ['bn' => 'প্রিন্ট করুন (A4)', 'en' => 'Print A4'],
            'printThermal' => ['bn' => 'প্রিন্ট করুন (পিওএস)', 'en' => 'Print POS'],
        ];
    }

    /**
     * @return array{cash: string, bkash: string, nagad: string, card: string, bank: string}
     */
    public static function paymentMethods(): array
    {
        return [
            'cash' => 'Cash',
            'bkash' => 'bKash',
            'nagad' => 'Nagad',
            'card' => 'Card',
            'bank' => 'Bank',
        ];
    }
}
