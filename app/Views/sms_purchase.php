<?php
// SMS Purchase Page
$userRole = $userRole ?? ($_SESSION['user']['role'] ?? 'manager');
$companyId = $_SESSION['user']['company_id'] ?? null;
$basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
?>

<div class="max-w-3xl mx-auto">
    <div class="mb-8">
        <a href="<?= $basePath ?>/dashboard/sms-settings" class="text-blue-600 hover:text-blue-800 mb-4 inline-flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to SMS Settings
        </a>
        <h1 class="text-3xl font-bold text-gray-900 mt-4">Purchase SMS Credits</h1>
        <p class="text-gray-600 mt-2">Buy SMS credits for your account. Credits are charged per SMS message.</p>
    </div>
    
    <div class="bg-white rounded-lg shadow-lg p-6">
        <!-- SMS Quantity Input -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Number of SMS Messages <span class="text-red-500">*</span>
            </label>
            <input 
                type="number" 
                id="sms-quantity-input" 
                min="0" 
                step="1" 
                value="0" 
                placeholder="Enter number of SMS messages"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-lg"
                required
            >
            <p class="text-xs text-gray-500 mt-1">
                Enter the number of SMS messages you want to purchase. Price is calculated per SMS.
            </p>
        </div>
        
        <!-- Pricing Summary -->
        <div id="pricing-summary" class="bg-teal-50 border border-teal-200 rounded-lg p-5 mb-6 hidden">
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700">SMS Messages:</span>
                    <span class="font-semibold text-gray-900 text-lg" id="summary-messages">0</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700">Price per SMS:</span>
                    <span class="text-sm text-gray-700" id="summary-unit-price">₵0.00</span>
                </div>
                <div class="border-t border-teal-300 pt-3 mt-3">
                    <div class="flex justify-between items-center">
                        <span class="text-base font-semibold text-gray-800">Total Amount:</span>
                        <span class="text-2xl font-bold text-teal-600" id="summary-total-amount">₵0.00</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Email Input -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Email Address <span class="text-red-500">*</span>
            </label>
            <input 
                type="email" 
                id="payment-email-input" 
                placeholder="your@email.com"
                value="<?php echo isset($_SESSION['user']['email']) ? htmlspecialchars($_SESSION['user']['email']) : ''; ?>"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                required
            >
            <p class="text-xs text-gray-500 mt-1">
                Payment receipt will be sent to this email
            </p>
        </div>
        
        <!-- Payment Button -->
        <div class="flex gap-3">
            <a 
                href="<?= $basePath ?>/dashboard/sms-settings"
                class="flex-1 bg-gray-200 text-gray-700 py-3 px-6 rounded-lg font-medium hover:bg-gray-300 transition text-center"
            >
                Cancel
            </a>
            <button 
                id="proceed-payment-btn"
                class="flex-1 bg-gradient-to-r from-teal-600 to-blue-600 text-white py-3 px-6 rounded-lg font-semibold hover:from-teal-700 hover:to-blue-700 transition flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                disabled
            >
                <span id="payment-btn-text">
                    <i class="fas fa-credit-card mr-2"></i>
                    Pay with Paystack
                </span>
                <span id="payment-loading" class="hidden">
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                    Processing...
                </span>
            </button>
        </div>
    </div>
</div>

<!-- Paystack Inline JS -->
<script src="https://js.paystack.co/v1/inline.js"></script>

<script>
(function() {
    const proceedBtn = document.getElementById('proceed-payment-btn');
    const paymentLoading = document.getElementById('payment-loading');
    const paymentBtnText = document.getElementById('payment-btn-text');
    const smsQuantityInput = document.getElementById('sms-quantity-input');
    const emailInput = document.getElementById('payment-email-input');
    const pricingSummary = document.getElementById('pricing-summary');
    
    // Per-SMS rate (loaded from API)
    let ratePerSMS = 0.05891; // Default: ₵0.05891 per SMS (38 GHS / 645 messages)
    let paystackPublicKey = '';
    
    // Load SMS rate on page load
    async function loadSMSRate() {
        try {
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            const response = await fetch(BASE + '/api/sms/pricing/rate', {
                headers: {
                    'Authorization': 'Bearer ' + token
                }
            });
            
            const data = await response.json();
            if (data.success && data.rate_per_sms) {
                ratePerSMS = parseFloat(data.rate_per_sms);
                updatePrice();
            }
        } catch (error) {
            console.error('Error loading SMS rate:', error);
            // Use default rate
            updatePrice();
        }
    }
    
    function updatePrice() {
        const quantity = parseInt(smsQuantityInput.value) || 0;
        const total = (quantity * ratePerSMS).toFixed(2);
        
        // Show/hide pricing summary based on quantity
        if (quantity > 0) {
            pricingSummary.classList.remove('hidden');
            document.getElementById('summary-messages').textContent = quantity.toLocaleString();
            document.getElementById('summary-unit-price').textContent = '₵' + ratePerSMS.toFixed(5);
            document.getElementById('summary-total-amount').textContent = '₵' + total;
            
            proceedBtn.disabled = false;
            proceedBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            pricingSummary.classList.add('hidden');
            proceedBtn.disabled = true;
            proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }
    
    async function initiatePurchase() {
        const quantity = parseInt(smsQuantityInput.value) || 0;
        
        if (quantity < 1) {
            alert('Please enter a valid number of SMS messages (minimum: 1)');
            smsQuantityInput.focus();
            return;
        }
        
        const email = emailInput.value.trim();
        if (!email || !email.includes('@')) {
            alert('Please enter a valid email address');
            emailInput.focus();
            return;
        }
        
        paymentLoading.classList.remove('hidden');
        paymentBtnText.classList.add('hidden');
        proceedBtn.disabled = true;
        
        try {
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            const response = await fetch(BASE + '/api/sms/paystack/initiate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    sms_quantity: quantity,
                    email: email
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.reference && data.public_key) {
                paystackPublicKey = data.public_key;
                
                // Open Paystack inline popup
                const handler = PaystackPop.setup({
                    key: paystackPublicKey,
                    email: email,
                    amount: Math.round(data.amount * 100), // Convert to kobo/pesewas
                    currency: 'GHS',
                    ref: data.reference,
                    metadata: {
                        payment_id: data.payment_id,
                        custom_fields: [
                            {
                                display_name: "SMS Messages",
                                variable_name: "sms_quantity",
                                value: quantity
                            }
                        ]
                    },
                    callback: function(response) {
                        // Payment successful - verify on server
                        verifyPayment(data.payment_id, response.reference);
                    },
                    onClose: function() {
                        // User closed the popup
                        paymentLoading.classList.add('hidden');
                        paymentBtnText.classList.remove('hidden');
                        proceedBtn.disabled = false;
                        alert('Payment was cancelled. Please try again when ready.');
                    }
                });
                
                handler.openIframe();
            } else {
                alert('Failed to initiate payment: ' + (data.error || 'Unknown error'));
                paymentLoading.classList.add('hidden');
                paymentBtnText.classList.remove('hidden');
                proceedBtn.disabled = false;
            }
        } catch (error) {
            console.error('Payment initiation error:', error);
            alert('Failed to initiate payment. Please try again.');
            paymentLoading.classList.add('hidden');
            paymentBtnText.classList.remove('hidden');
            proceedBtn.disabled = false;
        }
    }
    
    async function verifyPayment(paymentId, reference) {
        try {
            paymentLoading.classList.remove('hidden');
            paymentBtnText.classList.add('hidden');
            const token = localStorage.getItem('token') || localStorage.getItem('sellapp_token');
            const response = await fetch(BASE + '/api/sms/paystack/verify?payment_id=' + encodeURIComponent(paymentId) + '&reference=' + encodeURIComponent(reference), {
                headers: {
                    'Authorization': 'Bearer ' + token
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Redirect to success page
                window.location.href = BASE + '/dashboard/sms/payment-success?payment_id=' + encodeURIComponent(paymentId);
            } else {
                paymentLoading.classList.add('hidden');
                paymentBtnText.classList.remove('hidden');
                proceedBtn.disabled = false;
                alert('Payment verification failed: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Payment verification error:', error);
            paymentLoading.classList.add('hidden');
            paymentBtnText.classList.remove('hidden');
            proceedBtn.disabled = false;
            alert('Payment verification failed. Please contact support if your payment was successful.');
        }
    }
    
    // Event listeners
    smsQuantityInput?.addEventListener('input', updatePrice);
    proceedBtn?.addEventListener('click', initiatePurchase);
    
    // Load rate on page load
    loadSMSRate();
})();
</script>

