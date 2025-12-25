# MorPOS for OpenCart

[![OpenCart Version](https://img.shields.io/badge/OpenCart-3.0%2B-blue.svg)](https://www.opencart.com/)
[![PHP Version](https://img.shields.io/badge/PHP-7.1%2B-777bb4.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**MorPOS for OpenCart** is a secure and easy-to-use payment gateway extension that integrates the **Morpara MorPOS** payment system with OpenCart 3.x stores. Customers are redirected through a secure **Hosted Payment Page (HPP)** flow or can use an **Embedded Payment Form** when completing their orders.

> **📌 Note**: This branch supports **OpenCart 3.x**. If you're looking for **OpenCart 4.x** support, please visit the [4.x branch](https://github.com/morpara/morpos-opencart/tree/4.x).

## ✨ Features

- 🛒 **OpenCart Integration**: Seamlessly adds MorPOS as a payment method for OpenCart 3.x
- 🔒 **Secure Payments**: Hosted Payment Page (HPP) and Embedded Payment Form options
- 🌍 **Multi-Currency**: Supports TRY, USD, EUR currencies
- 💳 **Multiple Payment Options**: Credit cards, debit cards, and installment payments
- 🧪 **Sandbox Mode**: Complete test environment for development
- 🔧 **Easy Configuration**: Simple admin panel setup with connection testing
- 🛡️ **Security Features**: TLS 1.2+ requirement, signed API communication, cart/order validation
- 🌐 **Multi-Language**: Supports Turkish and English (extensible)

## 📋 Requirements

### Server Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| **OpenCart** | 3.0 | 3.0.4.1+ |
| **PHP** | 7.1 | 7.4+ |
| **TLS** | 1.2 | 1.3 |

### PHP Extensions

- `cURL` - Required for API communication
- `json` - Required for data processing
- `hash` - Required for security signatures
- `openssl` - Required for secure connections (OpenSSL 1.0.1+ for TLS 1.2 support)

### OpenCart Features

- **Admin Panel Access**: Required for plugin configuration
- **Database Access**: For conversation tracking table
- **HTTPS**: Recommended for production environments
- **Session SameSite Policy**: Automatically configured to 'Lax' during installation

## 🚀 Installation

### Method 1: Extension Installer (Recommended)

1. **Download the Extension**
   - Download the latest release ZIP file from [GitHub Releases](https://github.com/morpara/morpos-opencart/releases)

2. **Install via OpenCart Admin**
   - Go to **Extensions** → **Installer**
   - Click **Upload** and select the ZIP file
   - Wait for the upload to complete
   - Navigate to **Extensions** → **Extensions**
   - Choose **Extension Type**: **Payments**
   - Find **MorPOS Payment Gateway**
   - Click **Install** (green + button)

3. **Configure the Extension**
   - Click **Edit** (blue pencil icon)
   - Enter your MorPOS credentials
   - Click **Test Connection** to verify
   - Click **Save**

### Method 2: Manual Installation (Developers)

1. **Download the Extension**
   ```bash
   git clone https://github.com/morpara/morpos-opencart.git
   cd morpos-opencart
   ```

2. **Copy Files to OpenCart**

   ```bash
   # Copy admin files
   cp -r upload/admin/controller/extension/payment/morpos_gateway.php /path/to/opencart/admin/controller/extension/payment/
   cp -r upload/admin/language/en-gb/extension/payment/morpos_gateway.php /path/to/opencart/admin/language/en-gb/extension/payment/
   cp -r upload/admin/language/tr-tr/extension/payment/morpos_gateway.php /path/to/opencart/admin/language/tr-tr/extension/payment/
   cp -r upload/admin/model/extension/payment/morpos_gateway.php /path/to/opencart/admin/model/extension/payment/
   cp -r upload/admin/view/template/extension/payment/morpos_gateway.twig /path/to/opencart/admin/view/template/extension/payment/
   cp -r upload/admin/view/javascript/ /path/to/opencart/admin/view/javascript/
   cp -r upload/admin/view/stylesheet/ /path/to/opencart/admin/view/stylesheet/
   
   # Copy catalog files
   cp -r upload/catalog/controller/extension/payment/morpos_gateway.php /path/to/opencart/catalog/controller/extension/payment/
   cp -r upload/catalog/language/en-gb/extension/payment/morpos_gateway.php /path/to/opencart/catalog/language/en-gb/extension/payment/
   cp -r upload/catalog/language/tr-tr/extension/payment/morpos_gateway.php /path/to/opencart/catalog/language/tr-tr/extension/payment/
   cp -r upload/catalog/model/extension/payment/morpos_gateway.php /path/to/opencart/catalog/model/extension/payment/
   cp -r upload/catalog/model/extension/payment/morpos_conversation.php /path/to/opencart/catalog/model/extension/payment/
   cp -r upload/catalog/view/theme/default/ /path/to/opencart/catalog/view/theme/default/
   
   # Copy system library files
   cp -r upload/system/library/morpos/* /path/to/opencart/system/library/morpos/
   ```

3. **Set Correct Permissions**
   ```bash
   # Set file permissions (adjust path as needed)
   chmod 644 /path/to/opencart/admin/controller/extension/payment/morpos_gateway.php
   chmod 644 /path/to/opencart/catalog/controller/extension/payment/morpos_gateway.php
   chmod 644 /path/to/opencart/system/library/morpos/*.php
   ```

4. **Install via OpenCart Admin**
   - Navigate to **Extensions** → **Extensions**
   - Choose **Extension Type**: **Payments**
   - Find **MorPOS Payment Gateway**
   - Click **Install** (green + button)
   - Click **Edit** (blue pencil icon) to configure

### Method 3: FTP Upload

1. Download the extension files from [GitHub](https://github.com/morpara/morpos-opencart)
2. Extract the ZIP file
3. Using your FTP client, upload the contents of `upload/` to your OpenCart root directory
4. Maintain the directory structure during upload
5. Follow step 4 from Method 2 to complete installation

## ⚙️ Configuration

### 1. Access Settings

Navigate to **Extensions** → **Extensions** → **Choose Extension Type: Payments** → **MorPOS Payment Gateway** → **Edit**

### 2. Required Settings

Fill in the following mandatory fields:

| Field | Description | Example |
|-------|-------------|---------|
| **Merchant ID** | Your unique merchant identifier from MorPOS | `12345` |
| **Client ID** | OAuth client identifier from MorPOS | `your_client_id` |
| **Client Secret** | OAuth client secret from MorPOS | `your_client_secret` |
| **API Key** | Authentication key for API requests | `your_api_key` |

> **Where to get credentials?** Contact [Morpara Support](https://morpara.com/support) to obtain your merchant credentials.

### 3. Environment Settings

**Test Mode (Sandbox)**
- ✅ Enable for development/testing
- Uses sandbox endpoints
- No real transactions processed
- Test card numbers accepted

**Form Type**
Choose your payment interface:
- **Hosted**: Redirect to MorPOS payment page (recommended)
  - More secure
  - PCI compliance handled by MorPOS
  - Professional payment interface
- **Embedded**: Payment form within your site
  - Seamless checkout experience
  - Requires SSL certificate

**Order Status Settings**
- **Successful Order Status**: Status to set when payment succeeds (e.g., "Processing")
- **Failed Order Status**: Status to set when payment fails (e.g., "Failed")

**Sort Order**
- Determines the display order in checkout payment methods

### 4. Connection Test

After entering credentials:

1. Click **Test Connection** button in the admin panel
2. Wait for the connection test to complete
3. Verify green checkmark appears for successful connection
4. Check the system requirements table below:
   - ✅ PHP version compatibility
   - ✅ TLS version support
   - ✅ OpenCart version
   - ⚠️ Any warnings will be displayed with recommendations

### 5. Enable the Payment Method

- Set **Status** to **Enabled**
- Click **Save** to activate MorPOS on your store

## 🔧 Payment Flow

### Customer Checkout Process

1. **Cart Review**: Customer reviews items in cart
2. **Checkout**: Customer proceeds to checkout
3. **Payment Selection**: Customer selects MorPOS as payment method
4. **Payment Initiation**: 
   - System validates cart and creates order
   - Security checks ensure cart/order consistency
   - Unique conversation ID generated for tracking
5. **Payment Processing**:
   - **Hosted Mode**: Customer redirected to MorPOS payment page
   - **Embedded Mode**: Payment form loads within checkout page
6. **Payment Completion**: 
   - Customer completes payment
   - System receives callback from MorPOS
   - Order status updated automatically
7. **Confirmation**: Customer sees success/failure page

### Security Features

The plugin implements multiple security layers:

- **Cart/Order Validation**: Prevents amount manipulation during checkout
- **Conversation Tracking**: Each payment gets unique tracking ID
- **Signature Verification**: All API requests are signed with your Client Secret
- **Session Synchronization**: Automatic session handling for payment redirects
- **Amount Double-Check**: Validates cart total matches order total before payment
- **Auto-Rebuild Orders**: Detects and fixes potential tampering attempts

## 🛠️ Debugging

### Logging

Enable error logging in OpenCart:

1. **OpenCart 3.x:**
   ```php
   // In config.php and admin/config.php
   define('ERROR_LOG', '/path/to/your/error.log');
   ```

2. **Check Logs:**
   ```bash
   tail -f /path/to/your/error.log
   ```

3. **MorPOS Specific Logs:**
   The plugin writes security-related events:
   ```
   MorPOS Security: Cart/Order total mismatch detected
   MorPOS Gateway: Updated session SameSite policy to Lax
   ```

### Testing Payment Flows

**Test Cards (Sandbox Mode Only):**

Refer to your MorPOS sandbox documentation for test card numbers.

**Testing Checklist:**
- ✅ Successful payment flow
- ✅ Failed payment handling
- ✅ Network error scenarios
- ✅ Cart modification during payment
- ✅ Session timeout during payment
- ✅ Currency conversion (if applicable)
- ✅ Installment options
- ✅ Order status updates

## 🔍 Troubleshooting

### Common Issues

#### "An error occurred while initiating the payment"

**Causes & Solutions:**

1. **Incorrect Credentials**
   - Verify Merchant ID, Client ID, Client Secret, and API Key
   - Use **Test Connection** feature to validate

2. **Environment Mismatch**
   - Ensure Test Mode setting matches your credentials (sandbox vs production)

3. **TLS Requirements**
   - Server must support TLS 1.2 or higher
   - Check system requirements in admin panel

4. **cURL Issues**
   - Verify cURL extension is installed: `php -m | grep curl`
   - Check OpenSSL version: `php -r "echo OPENSSL_VERSION_TEXT;"`

#### Connection Test Fails

1. **Firewall/Network Issues**
   ```bash
   # Test connectivity from server
   curl -v https://finagopay-pf-sale-api-gateway.prp.morpara.com
   ```

2. **DNS Resolution**
   ```bash
   # Verify DNS resolves correctly
   nslookup finagopay-pf-sale-api-gateway.prp.morpara.com
   ```

3. **Server Time**
   - Ensure server time is accurate
   ```bash
   date
   ```

#### Payment Callback Fails

1. **Session Issues**
   - Plugin automatically sets SameSite policy to 'Lax'
   - Verify in database: `SELECT * FROM oc_setting WHERE key = 'config_session_samesite';`

2. **URL Rewriting**
   - Ensure OpenCart SEO URLs are properly configured
   - Test callback URL manually

3. **Conversation Table**
   - Check if `oc_morpos_conversation` table exists
   - Verify database permissions

#### Currency Not Supported

**Supported Currencies:**
- TRY (Turkish Lira) - Code: 949
- USD (US Dollar) - Code: 840
- EUR (Euro) - Code: 978

**Solution:** Configure OpenCart to use one of the supported currencies.

### System Requirements Check

The plugin includes a built-in system requirements checker in the admin panel:

| Component | Check |
|-----------|-------|
| **PHP Version** | 7.1+ required, 7.4+ recommended |
| **OpenCart Version** | 3.0+ required, 3.0.4.1+ recommended |
| **TLS Support** | 1.2+ required, 1.3+ recommended |

**Status Indicators:**
- 🟢 **Green**: Meets recommended requirements
- 🟡 **Yellow**: Meets minimum requirements, upgrade recommended
- 🔴 **Red**: Does not meet requirements, will not work

### Debug Mode

For detailed debugging:

1. **Enable OpenCart Error Display:**
   ```php
   // In config.php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   ```

2. **Check PHP Error Log:**
   ```bash
   tail -f /var/log/php_errors.log
   ```

3. **Check Web Server Logs:**
   ```bash
   # Apache
   tail -f /var/log/apache2/error.log
   
   # Nginx
   tail -f /var/log/nginx/error.log
   ```

## 🌐 Internationalization

The plugin supports multiple languages:

- **Turkish (tr-tr)**: Native support
- **English (en-gb)**: Default language

### Adding New Translations

1. **Copy Language File:**
   ```bash
   # For admin panel
   cp upload/admin/language/en-gb/extension/payment/morpos_gateway.php \
      upload/admin/language/[your-locale]/extension/payment/morpos_gateway.php
   
   # For catalog (customer-facing)
   cp upload/catalog/language/en-gb/extension/payment/morpos_gateway.php \
      upload/catalog/language/[your-locale]/extension/payment/morpos_gateway.php
   ```

2. **Translate Strings:**
   ```php
   <?php
   // Example: German (de-de)
   $_['heading_title'] = 'MorPOS Zahlungsgateway';
   $_['text_success'] = 'Einstellungen gespeichert!';
   // ... translate all strings
   ```

3. **Test Translation:**
   - Change OpenCart admin language
   - Verify all strings display correctly

### Available Translation Strings

The language files include:
- Admin panel labels and messages
- Customer-facing payment method text
- Error messages
- System requirement descriptions
- Toast notifications
- Button labels

## 🤝 Contributing

We welcome contributions! Here's how to get started:

### Development Setup

1. **Fork the Repository**
   ```bash
   git clone https://github.com/YOUR_USERNAME/morpos-opencart.git
   cd morpos-opencart
   ```

2. **Set Up Local Environment**
   - Install OpenCart 3.x locally
   - Copy plugin files to OpenCart directory
   - Configure database and web server

3. **Make Changes**
   - Follow OpenCart coding standards
   - Add appropriate documentation
   - Test with OpenCart 3.x

4. **Submit Pull Request**
   - Create feature branch: `git checkout -b feature/your-feature`
   - Commit changes: `git commit -m "Add your feature"`
   - Push branch: `git push origin feature/your-feature`
   - Open pull request on GitHub

### Coding Standards

- Follow [OpenCart Extension Development Guidelines](https://docs.opencart.com/)
- Maintain compatibility with OpenCart 3.x
- Include PHPDoc comments for functions and classes
- Write meaningful commit messages
- Follow OpenCart 3.x directory structure

### Testing Guidelines

Before submitting a pull request, test:

1. **Installation/Uninstallation**
   - Clean installation works
   - Uninstallation removes all data
   - Upgrade from previous version

2. **Payment Flows**
   - Successful payment (hosted & embedded)
   - Failed payment
   - Cancelled payment
   - Network errors

3. **Multi-Currency**
   - TRY, USD, EUR conversions
   - Currency display in admin and frontend

4. **Security**
   - Cart amount validation
   - Session handling
   - API signature verification

5. **Compatibility**
   - OpenCart 3.x
   - PHP 7.1, 7.2, 7.3, 7.4

## 📄 License

This project is licensed under the **MIT** License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

### Documentation
- Check this README thoroughly
- Review inline code comments
- Check [OpenCart Documentation](https://docs.opencart.com/)

### Issue Reporting
- **GitHub Issues**: [Report a Bug](https://github.com/morpara/morpos-opencart/issues)
- Include:
  - OpenCart version
  - PHP version
  - Plugin version
  - Error messages/logs
  - Steps to reproduce

### Commercial Support
- **Morpara Support**: [Contact Support](https://morpara.com/support)
- **Email**: support@morpara.com

### Community
- **OpenCart Forum**: Share experiences with other users
- **GitHub Discussions**: Ask questions and share tips

## 🙏 Acknowledgments

- **OpenCart Team** - For the excellent e-commerce platform
- **OpenCart Community** - For the robust ecosystem and support
- **Morpara** - For the secure payment infrastructure

## 🔐 Security

If you discover a security vulnerability, please email security@morpara.com instead of using the issue tracker. All security vulnerabilities will be promptly addressed.

### Security Features

- ✅ TLS 1.2+ encrypted communication
- ✅ Signed API requests with HMAC-SHA256
- ✅ Cart/order amount validation
- ✅ Conversation ID tracking
- ✅ Session security (SameSite policy)
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (output escaping)
- ✅ CSRF protection (OpenCart tokens)

---

**Made with ❤️ by [Morpara](https://morpara.com/)**

For more information about MorPOS payment solutions, visit [morpara.com](https://morpara.com/).
