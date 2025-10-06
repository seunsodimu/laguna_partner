# Separate Login Pages Documentation

## Overview

The Laguna Partners Portal now features **separate login pages** for different user types, providing a more organized and user-friendly authentication experience.

---

## 🎯 Login Page Structure

### **1. Main Landing Page** (`index.php`)
- **URL**: `http://localhost/laguna_partner/public/`
- **Purpose**: Portal selection page
- **Features**:
  - Two distinct login portals with visual separation
  - Color-coded design for easy identification
  - Hover effects and modern UI
  - Displays user type badges for each portal

### **2. Admin & Buyer Login** (`admin-buyer-login.php`)
- **URL**: `http://localhost/laguna_partner/public/admin-buyer-login.php`
- **User Types**: Admin, Buyer
- **Design**: Blue gradient theme (Professional/Corporate)
- **Features**:
  - Dropdown to select between Admin or Buyer
  - OTP-based authentication
  - Link to Vendor/Dealer portal
  - Color-coded badges (Red for Admin, Cyan for Buyer)

### **3. Vendor & Dealer Login** (`vendor-dealer-login.php`)
- **URL**: `http://localhost/laguna_partner/public/vendor-dealer-login.php`
- **User Types**: Vendor, Dealer
- **Design**: Green gradient theme (Business/Commerce)
- **Features**:
  - Dropdown to select between Vendor or Dealer
  - OTP-based authentication
  - Link to Admin/Buyer portal
  - Color-coded badges (Green for Vendor, Orange for Dealer)

---

## 🎨 Visual Design

### Color Schemes

| Portal | Primary Color | Secondary Color | Theme |
|--------|--------------|-----------------|-------|
| **Landing Page** | Purple (#667eea) | Violet (#764ba2) | Neutral/Welcome |
| **Admin & Buyer** | Navy Blue (#1e3c72) | Royal Blue (#2a5298) | Professional |
| **Vendor & Dealer** | Teal (#134e5e) | Green (#71b280) | Business |

### User Type Badges

| User Type | Badge Color | Icon |
|-----------|-------------|------|
| **Admin** | Red (#dc3545) | ⚙️ Gear |
| **Buyer** | Cyan (#0dcaf0) | 🛒 Cart |
| **Vendor** | Green (#198754) | 🏢 Building |
| **Dealer** | Orange (#fd7e14) | 🏪 Shop |

---

## 🔐 Authentication Flow

### Step 1: Portal Selection
1. User visits main landing page (`index.php`)
2. User selects appropriate portal:
   - **Admin/Buyer** → Blue card
   - **Vendor/Dealer** → Green card

### Step 2: User Type Selection
1. User is redirected to specific login page
2. User selects their exact user type from dropdown
3. User enters email address

### Step 3: OTP Verification
1. System sends 6-digit OTP to email
2. User enters OTP code
3. System verifies and logs in user
4. User is redirected to appropriate dashboard

---

## 📁 File Structure

```
public/
├── index.php                    # Main landing page (portal selection)
├── admin-buyer-login.php        # Admin & Buyer login page
├── vendor-dealer-login.php      # Vendor & Dealer login page
├── logout.php                   # Logout handler
├── admin/
│   └── dashboard.php           # Admin dashboard
├── buyer/
│   └── dashboard.php           # Buyer dashboard
├── vendor/
│   └── dashboard.php           # Vendor dashboard
└── dealer/
    └── dashboard.php           # Dealer dashboard
```

---

## 🔄 Navigation Between Portals

### From Landing Page
- Click **"Admin/Buyer Login"** → Goes to `admin-buyer-login.php`
- Click **"Vendor/Dealer Login"** → Goes to `vendor-dealer-login.php`

### From Admin/Buyer Login
- Click **"Go to Vendor/Dealer Login"** → Goes to `vendor-dealer-login.php`

### From Vendor/Dealer Login
- Click **"Go to Admin/Buyer Login"** → Goes to `admin-buyer-login.php`

### From Any Login Page
- Click **"Back"** (during OTP step) → Returns to same login page

---

## 🚀 Usage Examples

### Example 1: Admin Login
1. Visit: `http://localhost/laguna_partner/public/`
2. Click: **"Admin/Buyer Login"** (blue card)
3. Select: **"Admin"** from dropdown
4. Enter: Admin email address
5. Click: **"Send Login Code"**
6. Check email for OTP
7. Enter OTP and click **"Verify & Login"**
8. Redirected to: `/admin/dashboard.php`

### Example 2: Vendor Login
1. Visit: `http://localhost/laguna_partner/public/`
2. Click: **"Vendor/Dealer Login"** (green card)
3. Select: **"Vendor"** from dropdown
4. Enter: Vendor email address
5. Click: **"Send Login Code"**
6. Check email for OTP
7. Enter OTP and click **"Verify & Login"**
8. Redirected to: `/vendor/dashboard.php`

---

## 🔧 Technical Details

### Session Management
- Sessions are maintained across login pages
- OTP email and user type stored in session during authentication
- Session cleared after successful login
- Already logged-in users are automatically redirected to their dashboard

### Security Features
- ✅ OTP-based authentication (6-digit code)
- ✅ Email validation
- ✅ User type validation
- ✅ Session security
- ✅ OTP expiration (15 minutes)
- ✅ Automatic logout on session timeout

### Responsive Design
- ✅ Mobile-friendly layout
- ✅ Bootstrap 5 framework
- ✅ Responsive cards and buttons
- ✅ Touch-friendly interface

---

## 📊 User Type Routing

| User Type | Login Page | Dashboard URL |
|-----------|-----------|---------------|
| **Admin** | `admin-buyer-login.php` | `/admin/dashboard.php` |
| **Buyer** | `admin-buyer-login.php` | `/buyer/dashboard.php` |
| **Vendor** | `vendor-dealer-login.php` | `/vendor/dashboard.php` |
| **Dealer** | `vendor-dealer-login.php` | `/dealer/dashboard.php` |

---

## 🎯 Benefits of Separate Login Pages

### 1. **Better User Experience**
- Users immediately know which portal to use
- Reduced confusion with clear visual separation
- Faster login process with fewer options

### 2. **Improved Organization**
- Logical grouping of user types
- Admin/Buyer: Internal users (management/purchasing)
- Vendor/Dealer: External partners (suppliers/distributors)

### 3. **Enhanced Branding**
- Different color schemes for different user groups
- Professional appearance with distinct identities
- Modern, clean design

### 4. **Easier Maintenance**
- Separate files for each portal
- Independent styling and features
- Easier to customize per user group

### 5. **Scalability**
- Easy to add new user types
- Can add portal-specific features
- Flexible for future enhancements

---

## 🔗 Quick Links

### Direct Access URLs
```
Main Landing:        http://localhost/laguna_partner/public/
Admin/Buyer Login:   http://localhost/laguna_partner/public/admin-buyer-login.php
Vendor/Dealer Login: http://localhost/laguna_partner/public/vendor-dealer-login.php
```

### Dashboard URLs (after login)
```
Admin Dashboard:     http://localhost/laguna_partner/public/admin/dashboard.php
Buyer Dashboard:     http://localhost/laguna_partner/public/buyer/dashboard.php
Vendor Dashboard:    http://localhost/laguna_partner/public/vendor/dashboard.php
Dealer Dashboard:    http://localhost/laguna_partner/public/dealer/dashboard.php
```

---

## 🛠️ Customization

### Changing Colors
Edit the `<style>` section in each login page:

**Admin/Buyer Login** (`admin-buyer-login.php`):
```css
background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
```

**Vendor/Dealer Login** (`vendor-dealer-login.php`):
```css
background: linear-gradient(135deg, #134e5e 0%, #71b280 100%);
```

### Adding New User Types
1. Decide which portal the new user type belongs to
2. Add option to the dropdown in appropriate login page
3. Update validation in PHP code
4. Add routing in dashboard redirect logic

---

## 📝 Notes

- All login pages use the same OTP authentication system
- Email service must be configured for OTP delivery
- Session timeout is handled automatically
- Users can switch between portals using the provided links
- The main landing page (`index.php`) serves as the entry point

---

## ✅ Testing Checklist

- [ ] Landing page displays correctly
- [ ] Admin/Buyer login page accessible
- [ ] Vendor/Dealer login page accessible
- [ ] User type dropdowns work correctly
- [ ] OTP emails are sent successfully
- [ ] OTP verification works
- [ ] Users redirect to correct dashboards
- [ ] Portal switching links work
- [ ] Mobile responsive design works
- [ ] Already logged-in users redirect properly

---

## 🎉 Summary

The Laguna Partners Portal now features a **professional, organized login system** with:

✅ **3 separate pages**: Landing, Admin/Buyer, Vendor/Dealer
✅ **4 user types**: Admin, Buyer, Vendor, Dealer
✅ **Distinct visual themes**: Blue for Admin/Buyer, Green for Vendor/Dealer
✅ **Easy navigation**: Links between portals
✅ **Secure authentication**: OTP-based login
✅ **Modern design**: Responsive, clean, professional

Users can now easily identify and access their appropriate portal with a clear, intuitive interface!