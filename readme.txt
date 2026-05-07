=== Libro de Reclamaciones Peru ===
Contributors: eeast
Donate link: https://www.paypal.me/ELMERASTONITAS
Tags: libro de reclamaciones, peru, legal, compliance, forms.
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Complete Virtual Complaints Book system for businesses in Peru, compliant with Law 29571 and D.S. 011-2011-PCM.

== Description ==

**Libro de Reclamaciones Peru** is a professional plugin that allows Peruvian businesses to comply with the legal obligation to implement a Virtual Complaints Book, in accordance with **Law No. 29571 (Consumer Protection and Defense Code)** and **Supreme Decree No. 011-2011-PCM**.

= Key Features =

* **Complete Web Form**: Intuitive and responsive multi-step form for consumers to register their claims and complaints
* **Automatic PDF Generation**: Automatically creates complaint sheets in PDF format according to the official format (Annex I)
* **Claims Management**: Complete administration panel to manage, respond to, and track claims
* **Email Notifications**: Automatic email sending to consumer and administrator
* **Provider Digital Signature**: Ability to include digital signature in responses
* **Full Legal Compliance**: Includes all mandatory fields according to Peruvian regulations
* **Configurable Document Types**: Allows configuration of accepted document types (DNI, CE, RUC, Passport, etc.)
* **Multiple Locations**: Support for businesses with multiple locations or branches
* **Legal Representative Data**: Specific fields for minors with legal representative
* **Easy Implementation**: Use the shortcode `[libro_reclamaciones]` to add the form to any page.

= Regulatory Compliance =

This plugin complies with:

* **Law No. 29571** - Consumer Protection and Defense Code
* **Supreme Decree No. 011-2011-PCM** - Complaints Book Regulation
* **Official INDECOPI Format** - Annex I of the Regulation

= Form Features =

1. **Claimant Consumer Identification**
   - Complete personal data
   - Document type and number
   - Contact information
   - Legal representative data (if minor)

2. **Contracted Good Identification**
   - Type of good (product or service)
   - Purchase date
   - Claimed amount
   - Detailed description

3. **Claim Details**
   - Type: Claim or Complaint
   - Situation details
   - Consumer request

4. **Provider Response**
   - Space for administrative response
   - Attach evidence
   - Provider digital signature

= Administration Panel =

* **Inbox**: Complete view of all received claims
* **Individual Management**: Complete detail of each claim with response option
* **Flexible Configuration**: Customization of emails, digital signature, locations and document types
* **PDF Export**: Download of updated complaint sheets

= Ideal For =

* Online stores (WooCommerce, etc.)
* Service companies
* Restaurants and hotels
* Educational institutions
* Any business serving consumers in Peru

= Legal Disclaimer =

This plugin is provided as a technical tool to help businesses manage a Libro de Reclamaciones in a digital format.

The use, configuration, and implementation of this plugin are the sole responsibility of the website owner. It is the user's responsibility to ensure that the plugin is properly configured and that its usage complies with all applicable laws, regulations, and consumer protection requirements in their jurisdiction.

This plugin does not provide legal advice and does not guarantee automatic compliance with local regulations. The author is not responsible for any legal, administrative, or regulatory consequences arising from the incorrect use, misconfiguration, or outdated implementation of the plugin.

We strongly recommend that users review the applicable legal requirements and, if necessary, consult with a qualified legal professional.

== Installation ==

= Automatic Installation =

1. Go to **Plugins > Add New** in the WordPress dashboard
2. Search for "Libro de Reclamaciones Peru"
3. Click **Install Now**
4. Activate the plugin

= Manual Installation =

1. Download the plugin ZIP file
2. Go to **Plugins > Add New > Upload Plugin**
3. Select the downloaded ZIP file
4. Click **Install Now**
5. Activate the plugin

= Initial Setup =

1. Go to **Libro Reclamaciones > Settings**
2. In the **General** tab:
   - Configure the email for notifications
   - Add available locations (one per line)
   - Configure accepted document types
   - Upload the provider's digital signature (transparent PNG)
3. In the **Email** tab:
   - Customize email messages (optional)
4. Create a new page in WordPress
5. Add the shortcode `[libro_reclamaciones]` to the content
6. Publish the page

= Shortcode Usage =

Simply add to any page or post:

`[libro_reclamaciones]`

== Frequently Asked Questions ==

= Is it mandatory to have a Complaints Book in Peru? =

Yes, according to Law No. 29571 and D.S. 011-2011-PCM, all businesses serving consumers in Peru are required to have a Complaints Book, either physical or virtual.

= Does this plugin comply with Peruvian regulations? =

Yes, the plugin is specifically designed to comply with Law No. 29571 and Supreme Decree No. 011-2011-PCM, including all mandatory fields of the official format (Annex I).

= Can I customize the form? =

The form includes all legally required fields. You can customize the accepted document types, available locations, and email messages from the settings panel.

= How are PDFs generated? =

PDFs are automatically generated when a user submits a claim. The system uses the TCPDF library to create documents that comply with the official Annex I format of the Regulation.

= Are PDFs sent by email? =

Yes, a copy of the PDF is automatically sent to the consumer's email and a notification to the configured administrator.

= Can I respond to claims from the panel? =

Yes, from **Libro Reclamaciones > Inbox** you can view all claims, respond to them, attach evidence, and the system will generate an updated PDF that will be sent to the consumer.

= What format should the digital signature have? =

The signature must be a PNG file with transparent background. Recommended size: maximum 300px wide and 120px high. Suggested resolution: 150-300 DPI. Maximum size: 2MB.

= Does it work with WooCommerce? =

Yes, the plugin is independent and works with any WordPress site, including WooCommerce stores.

= Where are claims stored? =

Claims are stored in the WordPress database. PDF files are saved in the WordPress uploads folder (`wp-content/uploads/libro_reclamaciones/`).

= Can I have multiple locations? =

Yes, in **Settings > General** you can add all your locations or branches (one per line) and they will appear as options in the form.

= What is the deadline to respond to a claim? =

According to Peruvian law, the provider must respond to the claim within no more than **15 business days**.

== Screenshots ==

1. Public multi-step form - Step 1: Consumer identification
2. Public form - Step 2: Contracted good identification
3. Public form - Step 3: Claim details
4. Administration panel - Inbox with claims list
5. Administration panel - Detailed view of a claim
6. Administration panel - Claim response with attachments
7. General settings - Email, locations, document types and signature
8. Email settings - Message customization
9. Generated PDF - Official format compliant with regulations
10. Success message after submitting a claim

== Changelog ==

= 1.0.0 - 2026-01-06 =
* Initial plugin release
* Complete multi-step form for claim registration
* Automatic PDF generation compliant with official Annex I
* Administration panel for claims management
* Email notification system
* Multiple locations configuration
* Customizable document types configuration
* Provider digital signature support
* Legal representative support (minors)
* Claim response system with attachments
* Full compliance with Law 29571 and D.S. 011-2011-PCM

== Upgrade Notice ==

= 1.0.0 =
Initial version of the plugin. Install to comply with Peruvian Complaints Book regulations.

== Legal Support ==

This plugin is designed to help businesses comply with their legal obligations according to current Peruvian regulations. However, it is recommended to consult with a legal advisor to ensure full compliance with all specific obligations of your business.

**Applicable regulations:**
* Law No. 29571 - Consumer Protection and Defense Code
* Supreme Decree No. 011-2011-PCM - Complaints Book Regulation
* INDECOPI resolutions and directives

== Credits ==

Developed by **Elmer Astonitas**
Website: [https://elmerastonitas.com](https://elmerastonitas.com)

== Donations ==

If you find this plugin useful, consider making a donation:
[https://www.paypal.me/ELMERASTONITAS](https://www.paypal.me/ELMERASTONITAS)
