<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentTemplate;
use App\Models\User;

class DocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 自動尋找系統中的 Admin 用戶作為模板的擁有者
        $admin = User::where('role', 'admin')->first() ?? User::first();
        $adminId = $admin ? $admin->id : null;

        // 2. 這里放你最終設計好的完整發票 HTML
        $invoiceHtml = <<<'HTML'
            <style>* { box-sizing: border-box; } body {margin: 0;}*{box-sizing:border-box;}body{margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;}#ir8el{padding-top:12px;padding-right:15px;padding-bottom:12px;padding-left:15px;text-align:left;color:rgb(51, 65, 85);}#imo9h{padding-top:12px;padding-right:15px;padding-bottom:12px;padding-left:15px;text-align:center;color:rgb(51, 65, 85);width:15%;}#ih1rv{padding-top:12px;padding-right:15px;padding-bottom:12px;padding-left:15px;text-align:right;color:rgb(51, 65, 85);width:20%;}#idvfi{padding-top:12px;padding-right:15px;padding-bottom:12px;padding-left:15px;text-align:right;color:rgb(51, 65, 85);width:20%;}#is4ci{background-color:rgb(248, 250, 252);border-bottom-width:2px;border-bottom-style:solid;border-bottom-color:rgb(203, 213, 225);}#ioa1x{padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px;text-align:center;color:rgb(22, 101, 52);font-weight:bold;border-top-width:1px;border-right-width:1px;border-bottom-width:1px;border-left-width:1px;border-top-style:dashed;border-right-style:dashed;border-bottom-style:dashed;border-left-style:dashed;border-top-color:rgb(187, 247, 208);border-right-color:rgb(187, 247, 208);border-bottom-color:rgb(187, 247, 208);border-left-color:rgb(187, 247, 208);border-image-source:none;border-image-slice:100%;border-image-width:1;border-image-outset:0;border-image-repeat:stretch;}#iztmj{border-bottom-width:1px;border-bottom-style:solid;border-bottom-color:rgb(226, 232, 240);background-color:rgb(240, 253, 244);}#i0ag1{width:100%;border-collapse:collapse;font-size:14px;margin-top:0px;margin-right:0px;margin-bottom:10px;margin-left:0px;}#i8pgu-2{margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;color:rgb(15, 23, 42);font-size:36px;text-transform:uppercase;letter-spacing:2px;text-align:left;}#ia6q-2{width:30%;vertical-align:top;text-align:right;}#i03eut{width:100%;border-collapse:collapse;margin-top:0px;margin-right:0px;margin-bottom:10px;margin-left:0px;}#itqw19{margin-top:0px;margin-right:0px;margin-bottom:10px;margin-left:0px;color:rgb(15, 23, 42);font-size:14px;text-transform:uppercase;}#isa6rw{margin-top:0px;margin-right:0px;margin-bottom:5px;margin-left:0px;}#iphl0m{margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;}#i2y5k5{margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;}#iurpvv{margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;}#ijywxw{width:50%;vertical-align:top;}#iul715{width:100%;font-size:14px;color:rgb(71, 85, 105);}#ibq3mi{border-top-width:1px;border-top-style:solid;border-top-color:rgb(226, 232, 240);padding-top:15px;margin:0px 0px 10px 0px;}#i30lq4{font-size:12px;color:rgb(100, 116, 139);text-align:center;border-top-width:1px;border-top-style:solid;border-top-color:rgb(226, 232, 240);padding-top:20px;margin:0px 0px 10px 0px;}#ilej9y{margin-top:0px;margin-right:0px;margin-bottom:10px;margin-left:0px;color:rgb(15, 23, 42);font-size:14px;text-transform:uppercase;}#iayngk{color:rgb(71, 85, 105) !important;font-size:14px !important;margin-top:0px !important;margin-right:0px !important;margin-bottom:0px !important;margin-left:0px !important;display:block !important;line-height:1.5 !important;}#iahljh{margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;color:rgb(100, 116, 139);font-size:14px;line-height:1.6;}#izboca{padding-top:15px;padding-right:15px;padding-bottom:15px;padding-left:15px;background-color:rgb(248, 250, 252);border-left-width:4px;border-left-style:solid;border-left-color:rgb(203, 213, 225);}#ip8weh{width:50%;vertical-align:top;padding-right:30px;}#i5raeh{padding-top:8px;padding-right:15px;padding-bottom:8px;padding-left:15px;text-align:right;color:rgb(71, 85, 105);}#iezvk6{color:rgb(15, 23, 42) !important;font-size:14px !important;margin-top:0px !important;margin-right:0px !important;margin-bottom:0px !important;margin-left:0px !important;display:inline !important;line-height:normal !important;}#iasa1m{padding-top:8px;padding-right:15px;padding-bottom:8px;padding-left:15px;text-align:right;color:rgb(15, 23, 42);width:45%;}#i3t073{padding-top:8px;padding-right:15px;padding-bottom:8px;padding-left:15px;text-align:right;color:rgb(71, 85, 105);border-bottom-width:1px;border-bottom-style:solid;border-bottom-color:rgb(226, 232, 240);}#iq6zws{padding-top:8px;padding-right:15px;padding-bottom:8px;padding-left:15px;text-align:right;color:rgb(15, 23, 42);width:45%;border-bottom-width:1px;border-bottom-style:solid;border-bottom-color:rgb(226, 232, 240);}#il6vwm{padding-top:15px;padding-right:15px;padding-bottom:8px;padding-left:15px;text-align:right;font-weight:bold;color:rgb(15, 23, 42);}#ikgtla{color:rgb(15, 23, 42) !important;font-size:14px !important;margin-top:0px !important;margin-right:0px !important;margin-bottom:0px !important;margin-left:0px !important;display:inline !important;line-height:normal !important;}#ikvekj{padding-top:15px;padding-right:15px;padding-bottom:8px;padding-left:15px;text-align:right;font-weight:bold;color:rgb(15, 23, 42);}#i0yyxp{padding-top:8px;padding-right:15px;padding-bottom:8px;padding-left:15px;text-align:right;color:rgb(15, 23, 42);}#ildj25{color:rgb(15, 23, 42) !important;font-size:14px !important;margin-top:0px !important;margin-right:0px !important;margin-bottom:0px !important;margin-left:0px !important;display:inline !important;line-height:normal !important;}#isga9k{padding-top:8px;padding-right:15px;padding-bottom:8px;padding-left:15px;text-align:right;color:rgb(15, 23, 42);}#ifl8fq{padding-top:15px;padding-right:15px;padding-bottom:15px;padding-left:15px;text-align:right;font-weight:bold;font-size:18px;color:rgb(15, 23, 42);}#irwtog{color:rgb(15, 23, 42) !important;font-size:14px !important;margin-top:0px !important;margin-right:0px !important;margin-bottom:0px !important;margin-left:0px !important;display:inline !important;line-height:normal !important;}#io1pyi{padding-top:15px;padding-right:15px;padding-bottom:15px;padding-left:15px;text-align:right;font-weight:bold;font-size:18px;color:rgb(15, 23, 42);}#izqxt7{width:100%;border-collapse:collapse;font-size:14px;}#i6rbyg{width:50%;vertical-align:top;}#iusyp7{width:100%;border-collapse:collapse;margin:0px 0px 10px 0px;}#iso4l1{margin-top:0px;margin-right:0px;margin-bottom:10px;margin-left:0px;color:rgb(100, 116, 139);font-size:12px;text-transform:uppercase;}#iyehs7{color:rgb(15, 23, 42) !important;font-size:16px !important;font-weight:bold !important;margin-top:0px !important;margin-right:0px !important;margin-bottom:5px !important;margin-left:0px !important;display:block !important;}#ivam3g{margin-top:0px;margin-right:0px;margin-bottom:5px;margin-left:0px;font-weight:bold;color:rgb(15, 23, 42);font-size:16px;}#ixv9fg{color:rgb(71, 85, 105) !important;font-size:14px !important;margin-top:0px !important;margin-right:0px !important;margin-bottom:2px !important;margin-left:0px !important;display:block !important;}#i09mty{margin-top:0px;margin-right:0px;margin-bottom:2px;margin-left:0px;color:rgb(71, 85, 105);font-size:14px;}#ih8c5q{width:50%;vertical-align:top;padding-right:20px;}#itrq6l{margin-top:0px;margin-right:0px;margin-bottom:10px;margin-left:0px;color:rgb(100, 116, 139);font-size:12px;text-transform:uppercase;}#in8y2l{color:rgb(15, 23, 42) !important;font-size:16px !important;font-weight:bold !important;margin-top:0px !important;margin-right:0px !important;margin-bottom:5px !important;margin-left:0px !important;display:block !important;}#i250pl{margin-top:0px;margin-right:0px;margin-bottom:5px;margin-left:0px;font-weight:bold;color:rgb(15, 23, 42);font-size:16px;}#iy4g6l{color:rgb(71, 85, 105) !important;font-size:14px !important;margin-top:0px !important;margin-right:0px !important;margin-bottom:2px !important;margin-left:0px !important;display:block !important;}#i916os{margin-top:0px;margin-right:0px;margin-bottom:2px;margin-left:0px;color:rgb(71, 85, 105);font-size:14px;}#i4d52b{color:rgb(71, 85, 105) !important;font-size:14px !important;margin-top:0px !important;margin-right:0px !important;margin-bottom:2px !important;margin-left:0px !important;display:block !important;}#izoz2t{margin-top:0px;margin-right:0px;margin-bottom:2px;margin-left:0px;color:rgb(71, 85, 105);font-size:14px;}#i2n1uk{width:50%;vertical-align:top;padding-left:20px;}#idmsen{width:100%;border-collapse:collapse;margin-top:0px;margin-right:0px;margin-bottom:10px;margin-left:0px;}#i8pgu-2-2{margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;color:rgb(15, 23, 42);font-size:36px;text-transform:uppercase;letter-spacing:2px;}#iz8qce-2{text-transform:capitalize;}#ibrv9m-2{color:rgb(239, 68, 68);font-weight:bold;}#iwg1h3-2{text-transform:uppercase;font-weight:bold;background-color:rgb(254, 242, 242);color:rgb(185, 28, 28);padding-top:2px;padding-right:6px;padding-bottom:2px;padding-left:6px;border-top-left-radius:4px;border-top-right-radius:4px;border-bottom-right-radius:4px;border-bottom-left-radius:4px;}#i9xt1-2-2{margin-top:10px;color:rgb(100, 116, 139);font-size:14px;line-height:1.6;}#ia6q-2-2{width:30%;vertical-align:top;text-align:right;}</style>
            <body id="iscv">
                <table id="i03eut">
                    <tbody>
                        <tr>
                            <td id="ia6q-2">
                                <h1 id="i8pgu-2">Anysio technologies</h1>
                            </td>
                            <td id="ia6q-2-2">
                                <h1 id="i8pgu-2-2">INVOICE</h1>
                                <p id="i9xt1-2-2"><strong>Invoice #:</strong> <span data-variable="invoice_no" class="gjs-variable-tag">{{ invoice_no }}</span><br/>
                                <strong>Type:</strong> <span data-variable="invoice_type" id="iz8qce-2" class="gjs-variable-tag">{{ invoice_type }}</span><br/>
                                <strong>Period:</strong> <span data-variable="billing_period" class="gjs-variable-tag">{{ billing_period }}</span><br/>
                                <strong>Date:</strong> <span data-variable="invoice_date" class="gjs-variable-tag">{{ invoice_date }}</span><br/>
                                <strong>Due Date:</strong> <span data-variable="invoice_duedate" id="ibrv9m-2" class="gjs-variable-tag">{{ invoice_duedate }}</span><br/>
                                <strong>Status:</strong> <span data-variable="invoice_status" id="iwg1h3-2" class="gjs-variable-tag">{{ invoice_status }}</span></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <table id="idmsen">
                    <tbody>
                        <tr>
                            <td id="ih8c5q">
                                <h4 id="iso4l1">Billed To:</h4>
                                <p id="ivam3g"><span data-variable="tenant_name" id="iyehs7" class="gjs-variable-tag">{{ tenant_name }}</span></p>
                                <!--<p id="iaeyxs"><strong>Property:</strong> <span data-variable="property_unit_details" id="ilfqi9" class="gjs-variable-tag">{{ property_unit_details }}</span></p>-->
                                <!--<p id="if33jk"><strong>Phone:</strong> <span data-variable="tenant_phone" id="i33abf" class="gjs-variable-tag">{{ tenant_phone }}</span></p>-->
                                <p id="i09mty"><strong>Email:</strong> <span data-variable="tenant_email" id="ixv9fg" class="gjs-variable-tag">{{ tenant_email }}</span></p>
                            </td>
                            <td id="i2n1uk">
                                <h4 id="itrq6l">Pay To:</h4>
                                <p id="i250pl"><span data-variable="owner_name" id="in8y2l" class="gjs-variable-tag">{{ owner_name }}</span></p>
                                <p id="i916os"><strong>Phone:</strong> <span data-variable="owner_phone" id="iy4g6l" class="gjs-variable-tag">{{ owner_phone }}</span></p>
                                <p id="izoz2t"><strong>Email:</strong> <span data-variable="owner_email" id="i4d52b" class="gjs-variable-tag">{{ owner_email }}</span></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <table id="i0ag1">
                    <thead>
                        <tr id="is4ci">
                            <th id="ir8el">Description</th>
                            <th id="imo9h">Qty</th>
                            <th id="ih1rv">Unit Price</th>
                            <th id="idvfi">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="dynamic-invoice-tbody">
                        <tr id="iztmj">
                            <td colspan="4" id="ioa1x">⚙️ Dynamic Invoice Items Will Appear Here</td>
                        </tr>
                    </tbody>
                </table>
                
                <table id="iusyp7">
                    <tbody>
                        <tr>
                            <td id="ip8weh">
                                <div id="izboca">
                                    <h4 id="ilej9y">Remarks:</h4>
                                    <p id="iahljh"><span data-variable="remarks" id="iayngk" class="gjs-variable-tag">{{ remarks }}</span></p>
                                </div>
                            </td>
                            <td id="i6rbyg">
                                <table id="izqxt7">
                                    <tbody>
                                        <tr>
                                            <td id="i5raeh">Subtotal:</td>
                                            <td id="iasa1m">RM <span data-variable="total_amount" id="iezvk6" class="gjs-variable-tag">{{ total_amount }}</span></td>
                                        </tr>
                                        <tr>
                                            <td id="i3t073">Tax (0%):</td>
                                            <td id="iq6zws">RM 0.00</td>
                                        </tr>
                                        <tr>
                                            <td id="il6vwm">Total Amount:</td>
                                            <td id="ikvekj">RM <span data-variable="total_amount" id="ikgtla" class="gjs-variable-tag">{{ total_amount }}</span></td>
                                        </tr>
                                        <tr>
                                            <td id="i0yyxp">Amount Paid:</td>
                                            <td id="isga9k">- RM <span data-variable="amount_paid" id="ildj25" class="gjs-variable-tag">{{ amount_paid }}</span></td>
                                        </tr>
                                        <tr>
                                            <td id="ifl8fq">Balance Due:</td>
                                            <td id="io1pyi">RM <span data-variable="amount_balance" id="irwtog" class="gjs-variable-tag">{{ amount_balance }}</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div id="ibq3mi">
                    <h4 id="itqw19">Payment Methods</h4>
                    <table id="iul715">
                        <tbody>
                            <tr>
                                <td id="ijywxw">
                                    <p id="isa6rw"><strong>Bank Transfer:</strong></p>
                                    <p id="iphl0m">Bank: Maybank</p>
                                    <p id="i2y5k5">Account Name: Anysio Technologies</p>
                                    <p id="iurpvv">Account No: 5580 4283 0633</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p id="i30lq4">Payment is due within 7 days. Thank you for your business!</p>
            </body> 
        HTML;

        $tosHtml = <<<'HTML'
            <style>* { box-sizing: border-box; } body {margin: 0;}#iteu{margin:10px 0;line-height:1.5;color:#333;}#imlg{font-size:20px;color:#2c3e50;border-bottom:2px solid #eee;padding-bottom:5px;}#iot7s{margin:10px 0;line-height:1.5;color:#333;}#iivki{font-size:20px;color:#2c3e50;border-bottom:2px solid #eee;padding-bottom:5px;}#ijnsw{margin:10px 0;line-height:1.5;color:#333;}#im1dl{margin-bottom:5px;}#itrif{margin-bottom:5px;}#iqp45{margin-bottom:5px;}#ievj5{margin:15px 0;padding-left:20px;line-height:1.6;color:#333;}#ik1ft{font-size:20px;color:#2c3e50;border-bottom:2px solid #eee;padding-bottom:5px;}#ivqqy{margin-bottom:5px;}#i5b7f{margin-bottom:5px;}#ifrwr{margin-bottom:5px;}#ivtwu{margin:15px 0;padding-left:20px;line-height:1.6;color:#333;}#igdzj{font-size:20px;color:#2c3e50;border-bottom:2px solid #eee;padding-bottom:5px;}#ihxmf{margin-bottom:5px;}#imfrg{margin-bottom:5px;}#izbkx{margin-bottom:5px;}#ik154{margin:15px 0;padding-left:20px;line-height:1.6;color:#333;}#i65rf{font-size:20px;color:#2c3e50;border-bottom:2px solid #eee;padding-bottom:5px;}#i4kjr{margin:10px 0;line-height:1.5;color:#333;}#iisg9{font-size:20px;color:#2c3e50;border-bottom:2px solid #eee;padding-bottom:5px;}#izm5i{margin:10px 0;line-height:1.5;color:#333;}#iikmh{margin-bottom:5px;}#ia6tj{margin-bottom:5px;}#in8zv{margin-bottom:5px;}#ign11{margin:15px 0;padding-left:20px;line-height:1.6;color:#333;}#izt5x{font-size:20px;color:#2c3e50;border-bottom:2px solid #eee;padding-bottom:5px;}#ic1ub{margin:10px 0;line-height:1.5;color:#333;}#ipa2t{font-size:20px;color:#2c3e50;border-bottom:2px solid #eee;padding-bottom:5px;}#i8s8kh{margin:10px 0;line-height:1.5;color:#333;}#i13mia{font-size:20px;color:#2c3e50;border-bottom:2px solid #eee;padding-bottom:5px;}#iardqc{margin:10px 0;line-height:1.5;color:#333;}</style>
            <body id="iibf">
                <h1 id="i3th">Terms of Service</h1>
                <p id="iot7s">These Terms of Service ("Terms") govern your access to and use of our Property Management Platform ("the Platform"). By registering for an account or using our services, you agree to be bound by these Terms.</p>
                
                <h2 id="imlg">1. Acceptance of Terms</h2>
                <p id="iteu">By creating an account, you confirm that you are at least 18 years old and have the legal capacity to enter into a binding agreement. If you are using the Platform on behalf of an organization, you represent that you have the authority to bind that organization to these Terms.</p>
                
                <h2 id="ik1ft">2. Nature of the Platform</h2>
                <p id="ijnsw">Our Platform is a property management tool designed to assist landlords, tenants, and property managers in tracking leases, generating invoices, and recording payments.<br/></p>
                <ul id="ivtwu">
                    <li id="ivqqy">We act solely as a technology service provider.</li>
                    <li id="i5b7f">We are not a party to the lease agreements executed between landlords and tenants.</li>
                    <li id="ifrwr">We provide no guarantee regarding the legality, enforceability, or accuracy of the lease agreements uploaded to our system.</li>
                </ul>
                
                <h2 id="igdzj">3. User Obligations</h2>
                <ul id="ik154">
                    <li id="ihxmf"><b>Accuracy of Data: </b>You agree to provide accurate, current, and complete information during the registration process and when entering lease/tenant data.</li>
                    <li id="imfrg"><b>Account Security:</b> You are responsible for maintaining the confidentiality of your login credentials. Any activity occurring under your account is your sole responsibility.</li>
                    <li id="izbkx"><b>Lawful Use: </b>You agree not to use the Platform for any unlawful purposes, including the storage or processing of fraudulent documents or unauthorized financial data.</li>
                </ul>
                
                <h2 id="iivki">4. Payments and Billing</h2>
                <ul id="ign11">
                    <li id="iikmh">The Platform facilitates invoice generation based on the data entered by the user.</li>
                    <li id="ia6tj">The Platform does not process payments directly unless explicitly integrated with a third-party payment gateway.</li>
                    <li id="in8zv">Any disputes regarding rental payments, late fees, or refunds must be resolved directly between the landlord and the tenant.</li>
                </ul>
                
                <h2 id="i65rf">5. Intellectual Property</h2>
                <p id="ic1ub">All content, features, and functionality of the Platform (including text, graphics, and software) are the exclusive property of our company. You may not modify, reproduce, or distribute any part of the Platform without our prior written consent.</p>
                
                <h2 id="iisg9">6. Limitation of Liability</h2>
                <p id="i4kjr">To the maximum extent permitted by applicable law in Malaysia:</p>
                <ul id="ievj5">
                    <li id="im1dl">The Platform is provided on an "as-is" and "as-available" basis.</li>
                    <li id="itrif">We shall not be liable for any indirect, incidental, or consequential damages resulting from the use of, or inability to use, our services.</li>
                    <li id="iqp45">We are not liable for any discrepancies in financial records caused by incorrect data entry by the user.</li>
                </ul>
                
                <h2 id="izt5x">7. Indemnification</h2>
                <p id="i8s8kh">You agree to indemnify and hold harmless our company, its officers, and employees from any claims, losses, or expenses arising from your violation of these Terms or your misuse of the Platform.</p>
                
                <h2 id="i13mia">8. Termination</h2>
                <p id="iardqc">We reserve the right to suspend or terminate your account at any time, without prior notice, if you violate these Terms or engage in fraudulent activities.</p>
                
                <h2 id="ipa2t">9. Governing Law</h2>
                <p id="izm5i">These Terms shall be governed by and construed in accordance with the laws of Malaysia. Any disputes arising from these Terms shall be subject to the exclusive jurisdiction of the courts of Malaysia.</p>
            </body>
        HTML;

        $privacyHtml = <<<'HTML'
            <style>* { box-sizing: border-box; } body {margin: 0;}#i18u{font-size:20px;color:#2c3e50;border-bottom:2px solid #eee;padding-bottom:5px;}#idbb{margin:10px 0;line-height:1.5;color:#333;}#i3cjl{margin:10px 0;line-height:1.5;color:#333;}#ihidc{margin-bottom:5px;}#idu4l{margin-bottom:5px;}#isjxr{margin:15px 0;padding-left:20px;line-height:1.6;color:#333;}#ioshh{font-size:20px;color:#2c3e50;border-bottom:2px solid #eee;padding-bottom:5px;}#i733u{margin:10px 0;line-height:1.5;color:#333;}#i1dil{margin-bottom:5px;}#i0d5h{margin-bottom:5px;}#id2wj{margin-bottom:5px;}#ixq1c{margin:15px 0;padding-left:20px;line-height:1.6;color:#333;}#i93p7{font-size:20px;color:#2c3e50;border-bottom:2px solid #eee;padding-bottom:5px;}#inpld{margin-bottom:5px;}#ix0qh{margin-bottom:5px;}#ih61j{margin-bottom:5px;}#if6r1{margin:15px 0;padding-left:20px;line-height:1.6;color:#333;}#iypd6{font-size:20px;color:#2c3e50;border-bottom:2px solid #eee;padding-bottom:5px;}#i1wmv{margin:10px 0;line-height:1.5;color:#333;}#izfe6{font-size:20px;color:#2c3e50;border-bottom:2px solid #eee;padding-bottom:5px;}#ihwk8{margin:10px 0;line-height:1.5;color:#333;}</style>
            <body id="ioy9">
                <h1 id="i7wg">Privacy Policy</h1>
                <p id="idbb">Anysio Technologies ("we," "us," or "our") is committed to protecting the privacy and security of your personal data. This Privacy Policy explains how we collect, use, and store information through our Property Management System (PMS).</p>
                
                <h2 id="ioshh">1. Data We Collect</h2>
                <p id="i733u">We collect information necessary to provide our property management services:</p>
                <ul id="ixq1c">
                    <li id="i1dil"><b>Account Data:</b> Registration details, including name, email, and contact information.</li>
                    <li id="i0d5h"><b>Property &amp; Tenancy Data: </b>Information regarding properties, unit details, lease agreements, tenant profiles, and rental payment records.</li>
                    <li id="id2wj"><b>Usage Metadata: </b>Technical data regarding your interaction with our platform to ensure system performance and security.</li>
                </ul>
                
                <h2 id="i93p7">2. How We Use Your Data</h2>
                <p id="i3cjl">Your data is processed strictly for the following purposes:</p>
                <ul id="if6r1">
                    <li id="inpld">To facilitate property management, lease tracking, and rental invoicing.</li>
                    <li id="ix0qh">To provide administrative support and account management.</li>
                    <li id="ih61j">To maintain platform security and improve user experience.</li>
                </ul>
                
                <h2 id="iypd6">3. Data Sharing</h2>
                <ul id="isjxr">
                    <li id="ihidc">With essential service providers (e.g., secure cloud hosting) under strict confidentiality.</li>
                    <li id="idu4l">When required by law, regulation, or legal process in Malaysia.</li>
                </ul>
                
                <h2 id="izfe6">4. Security &amp; Retention</h2>
                <p id="ihwk8">We implement industry-standard technical measures to protect your data. We retain data only for as long as necessary to fulfill our service obligations and comply with Malaysian legal requirements.</p>
                
                <h2 id="i18u">5. Contact Us</h2>
                <p id="i1wmv">If you have questions regarding this Privacy Policy, please contact us:<br/><br/><b>WhatsApp:</b> 011-1088 0912<br/><b>Email: </b>kaifengchoong@gmai.com</p>
            </body>
        HTML;

        // 🌟 新增：這里放你最終設計好的完整 Receipt HTML
        $receiptHtml = <<<'HTML'
            <style>
            * { box-sizing: border-box; } 
            body { margin: 0; font-family: sans-serif; }
            #i4bts { margin: 0; color: #059669; font-size: 32px; text-transform: uppercase; letter-spacing: 2px; text-align: center; }
            #ibm2n, #iuoi6 { margin-top: 5px; margin-bottom: 0; color: #64748b; font-size: 14px; text-align: center; }
            #igjb { width: 50%; vertical-align: top; text-align: right; }
            #itwu, #ijpdl, #ipfso { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
            #im5cn, #id2fa { margin: 0 0 10px 0; color: #64748b; font-size: 12px; text-transform: uppercase; }
            #igdq3, #i5mcq { margin: 0 0 5px 0; font-weight: bold; color: #0f172a; font-size: 16px; }
            #iolms, #iil9u { margin: 0; color: #475569; font-size: 14px; line-height: 1.5; }
            #iaauf { width: 50%; vertical-align: top; padding-right: 20px; }
            #i7c1i { width: 50%; vertical-align: top; padding-left: 20px; }
            #itdc2 { margin: 0 0 10px 0; color: #334155; font-size: 14px; }
            #id9th { background-color: #f8fafc; border-bottom: 2px solid #cbd5e1; }
            #iy18e { padding: 12px 15px; text-align: left; color: #334155; }
            #ig94e { padding: 12px 15px; text-align: center; color: #334155; width: 15%; }
            #i2d1z, #it8rf2 { padding: 12px 15px; text-align: right; color: #334155; width: 20%; }
            #i0ctj2 { border-bottom: 1px solid #e2e8f0; background-color: #ecfdf5; }
            #ilopnj { padding: 20px; text-align: center; color: #065f46; font-weight: bold; border: 1px dashed #a7f3d0; }
            #ijj314 { width: 40%; border-collapse: collapse; margin-left: auto; margin-bottom: 40px; font-size: 14px; }
            #i166jc { padding: 10px 15px; text-align: right; font-weight: 500; color: #475569; }
            #igu684 { padding: 10px 15px; text-align: right; font-weight: 500; color: #0f172a; }
            #itzzvo { padding: 15px; text-align: right; font-weight: bold; font-size: 16px; color: #0f172a; border-top: 1px solid #e2e8f0; }
            #i4s2lm { padding: 15px; text-align: right; font-weight: bold; font-size: 18px; color: #059669; background-color: #ecfdf5; border: 1px solid #a7f3d0; border-top: 1px solid #e2e8f0; }
            #ihg2q6 { font-size: 12px; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 40px; }
            </style>
            <body id="iz07">
                <table id="itwu">
                    <tbody>
                        <tr>
                            <td id="igjb">
                                <h1 id="i4bts">OFFICIAL RECEIPT</h1>
                                <p id="ibm2n">Receipt No: {{ receipt_no }}</p>
                                <p id="iuoi6">Date: {{ receipt_date }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table id="ijpdl">
                    <tbody>
                        <tr>
                            <td id="iaauf">
                                <h4 id="im5cn">Received From (Customer):</h4>
                                <p id="igdq3"><span data-variable="user_name">{{ user_name }}</span></p>
                                <p id="iolms">
                                    <strong>Phone:</strong> <span data-variable="user_phone">{{ user_phone }}</span><br/>
                                    <strong>Email:</strong> <span data-variable="user_email">{{ user_email }}</span>
                                </p>
                            </td>
                            <td id="i7c1i">
                                <h4 id="id2fa">Issued By (Biller):</h4>
                                <p id="i5mcq">Anysio Technologies</p>
                                <p id="iil9u">
                                    <strong>Phone:</strong> <span data-variable="company_phone">{{ company_phone }}</span><br/>
                                    <strong>Email:</strong> <span data-variable="company_email">{{ company_email }}</span>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <h4 id="itdc2">Payment For:</h4>
                <table id="ipfso">
                    <thead>
                        <tr id="id9th">
                            <th id="iy18e">Description</th>
                            <th id="ig94e">Qty</th>
                            <th id="i2d1z">Unit Price</th>
                            <th id="it8rf2">Amount Paid</th>
                        </tr>
                    </thead>
                    <tbody id="dynamic-receipt-tbody">
                        <tr id="i0ctj2">
                            <td colspan="4" id="ilopnj">
                                ⚙️ Dynamic Receipt Items Will Appear Here
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table id="ijj314">
                    <tbody>
                        <tr>
                            <td id="i166jc">Subtotal:</td>
                            <td id="igu684">RM {{ subtotal_amount }}</td>
                        </tr>
                        <tr>
                            <td id="itzzvo">Total Paid:</td>
                            <td id="i4s2lm">RM {{ total_amount }}</td>
                        </tr>
                    </tbody>
                </table>
                <p id="ihg2q6">
                    This is a computer-generated receipt. No signature is required.<br/>
                    Thank you for your business!
                </p>
            </body>
        HTML;



        
        // 使用 Method B：自動抓取系統 Admin 作為模板擁有者，若找不到則回退為 null
        DocumentTemplate::updateOrCreate(
            [
                'category' => 'invoice',
                'status'   => 'active',
                'user_id'  => $adminId,
            ],
            [
                'title'         => 'Standard System Invoice Template',
                'details'       => 'Default standard system invoice template for lease billing.',
                'html_template' => $invoiceHtml,
            ]
        );

        DocumentTemplate::updateOrCreate(
            [
                'category' => 'tos',
                'status'   => 'active',
                'user_id'  => $adminId,
            ],
            [
                'title'         => 'Standard System TOS Template',
                'details'       => '',
                'html_template' => $tosHtml,
            ]
        );

        DocumentTemplate::updateOrCreate(
            [
                'category' => 'privacy',
                'status'   => 'active',
                'user_id'  => $adminId,
            ],
            [
                'title'         => 'Standard System Privacy Template',
                'details'       => '',
                'html_template' => $privacyHtml,
            ]
        );

        // 🌟 新增：這里執行 Receipt 模板的 updateOrCreate 邏輯
        DocumentTemplate::updateOrCreate(
            [
                'category' => 'receipt',
                'status'   => 'active',
                'user_id'  => $adminId,
            ],
            [
                'title'         => 'Standard System Receipt Template',
                'details'       => 'Default standard system receipt template for payments.',
                'html_template' => $receiptHtml,
            ]
        );
    }
}