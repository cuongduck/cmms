# CMMS - Computerized Maintenance Management System

Hệ thống quản lý bảo trì thiết bị cho ngành sản xuất thực phẩm được xây dựng trên nền tảng PHP kết hợp với JavaScript và MariaDB.

## 🎯 Mục đích

Quản lý toàn diện thiết bị sản xuất trong dây chuyền thực phẩm, từ việc theo dõi vị trí, lịch bảo trì, quản lý spare part đến báo cáo hiệu suất thiết bị.

## 🏭 Môi trường áp dụng

- **Dây chuyền sản xuất thực phẩm gồm 3 nghành hàng là : nghành mì, nghành phở và nghành gia vị và gồm xưởng F2 và F3 trong đó xưởng F2 gồm 4 line mì 1,2,3,4. xưởng F3 gồm 4 line mì 5,6,7,8 và 2 line phở 1 và phở 2. Xưởng gia vị củng nằm ở xưởng F3 và không phân theo line 

## 🛠️ Yêu cầu hệ thống

- **PHP**: 8.0+
- **Database**: MariaDB 10.11+
- **Web Server**: Apache/Nginx
- **Extensions**: PDO, MySQLi, JSON, FileInfo

## 📦 Cài đặt

1. **Clone repository**
```bash
git clone https://github.com/cuongduck/cmms.git
cd cmms
```
2. **Cấu hình kết nối**
```php
// config/database.php
$host = 'your_db_host';      
$dbname = 'cmms';           
$username = 'your_username'; 
$password = 'your_password';
```

3. **Phân quyền thư mục**
```bash
chmod -R 755 assets/uploads/
chown -R www-data:www-data assets/uploads/
```

## 🏗️ Cấu trúc dự án

```
cmms/
├── assets
│   ├── css
│   │   ├── bom.css
│   │   ├── equipment-view.css
│   │   ├── equipment.css
│   │   ├── structure.css
│   │   └── style.css
│   ├── images
│   │   └── logo.png
│   ├── js
│   │   ├── bom-parts.js
│   │   ├── bom.js
│   │   ├── equipment-add.js
│   │   ├── equipment-edit.js
│   │   ├── equipment-view.js
│   │   ├── equipment.js
│   │   ├── main.js
│   │   └── structure.js
│   └── uploads
│       ├── bom
│       ├── equipment_images
│       ├── equipment_manuals
│       ├── equipment_settings
│       ├── manuals
│       └── temp
├── config
│   ├── auth.php
│   ├── config.php
│   ├── database.php
│   ├── equipment_helpers.php
│   └── functions.php
├── includes
│   ├── footer.php
│   ├── header.php
│   └── sidebar.php
├── index.php
├── login.php
├── logout.php
└── modules
    ├── bom
    │   ├── add.php
    │   ├── api
    │   │   ├── bom.php
    │   │   ├── export.php
    │   │   └── parts.php
    │   ├── config.php
    │   ├── edit.php
    │   ├── imports
    │   │   ├── bom_import.php
    │   │   └── parts_import.php
    │   ├── index.php
    │   ├── parts
    │   │   ├── add.php
    │   │   ├── edit.php
    │   │   ├── index.php
    │   │   └── view.php
    │   ├── reports
    │   │   ├── shortage_report.php
    │   │   └── stock_report.php
    │   └── view.php
    ├── equipment
    │   ├── add.php
    │   ├── api
    │   │   └── equipment.php
    │   ├── edit.php
    │   ├── index.php
    │   ├── uploads
    │   │   └── equipment
    │   │       └── images
    │   └── view.php
    ├── inventory
    │   └── transactions.php
    └── structure
        ├── api
        │   ├── areas.php
        │   ├── equipment_groups.php
        │   ├── industries.php
        │   ├── lines.php
        │   ├── machine_types.php
        │   └── workshops.php
        ├── index.php
        └── views
            ├── areas.php
            ├── equipment_groups.php
            ├── industries.php
            ├── lines.php
            ├── machine_types.php
            └── workshops.php


## 🎛️ Modules chính

### 📋 Structure Management
Quản lý cơ cấu tổ chức sản xuất theo hierarchy:
- **Industry** (Ngành): Mì, phở, gia vị
- **Workshop** (Phân xưởng): xưởng F1, xưởng F2
- **Production Line** (Dây chuyền): Line 1, Line 2, Line 3, Line 4, Line 5, Line 6, Line 7, Line 8, Phở 1, Phở 2
- **Area** (Khu vực): Khu đóng gói, khu công nghệ. Khu vực này là dùng chung cho các line sản xuất 

### 🏭 Equipment Management
Quản lý thiết bị với thông tin đầy đủ:
- **Thông tin cơ bản**: Mã máy, tên, model, serial number
- **Vị trí**: Industry → Workshop → Line → Area
- **Chủ quản**: Owner chính và backup owner
- **Bảo trì**: Frequency, criticality, maintenance history
- **Files**: Hình ảnh, manual, settings images
- **Trạng thái**: Active, Inactive, Maintenance, Broken

### 📝 BOM (Bill of Materials)
Quản lý danh sách vật tư cho từng loại máy:
- **Machine BOM**: Định nghĩa BOM cho từng machine type
- **BOM Items**: Chi tiết parts cần thiết với quantity, position
- **Version control**: Theo dõi thay đổi BOM qua các version
- **Stock integration**: Liên kết với inventory để check stock

### 🔧 Parts Management
Quản lý phụ tùng và nhà cung cấp:
- **Parts catalog**: Mã parts, tên, specifications
- **Multiple suppliers**: Nhiều nhà cung cấp cho 1 part
- **Stock levels**: Min/max stock, lead time
- **Purchase requests**: Tạo yêu cầu mua hàng tự động

### 📊 Inventory Tracking
Theo dõi xuất nhập kho:
- **Stock levels**: Onhand quantity theo từng locator
- **Transactions**: Lịch sử xuất nhập với reason codes
- **Integration**: Liên kết với parts và BOM
- **Onhand** : tồn kho hiện tại, tìm kiếm vật tư trong kho. biết được nó là vật tư ngoài bom, hay trong bom

### 📊 Maintenance
Kế hoạch bảo trì và CLIT thiết bị:


## 🔧 Database Configuration

### Kết nối Database
```php
// Singleton pattern - kết nối duy nhất
class Database {
    private $host = '10.18.15.43';    // IP server MariaDB
    private $dbname = 'cmms';          // Database name
    private $username = 'cf';          // DB user
    private $charset = 'utf8mb4';      // UTF-8 support Vietnamese
}

// Sử dụng
$db = Database::getInstance();
$equipment = $db->fetchAll("SELECT * FROM equipment WHERE status = ?", ['active']);
```


## 👥 Phân quyền hệ thống

- **Admin**: Full access tất cả modules
- **Supervisor**: xem được tất cả module nhưng chỉ có quyền thêm BOM, part và xoá được những cái mình tạo, tạo yêu cầu công việc
- **Production Manager**: xem được tất cả module nhưng có quyền thêm , sửa xoá
- **User**: xem được tất cả module nhưng chỉ thêm sữa xoá được mục thực hiện công việc bảo trì do mình tạo

## 🚀 Sử dụng

1. **Đăng nhập** với username/password được cấp
2. **Setup cơ cấu tổ chức** trong Structure module
3. **Thêm machine types** và equipment groups
4. **Tạo equipment records** với đầy đủ thông tin
5. **Định nghĩa BOM** cho từng loại máy
6. **Import parts catalog** và supplier information
7. **Theo dõi maintenance** và inventory transactions

## 🔄 Workflow chính

1. **Equipment Registration** → Setup hierarchy và thông tin máy
2. **BOM Definition** → Định nghĩa parts list cho từng machine type  
3. **Parts Management** → Quản lý catalog và suppliers
4. **Inventory Control** → Theo dõi stock và transactions
5. **Maintenance Planning** → Schedule và track maintenance activities

## 📈 Reports & Analytics

- **Equipment Status Report**: Trạng thái toàn bộ thiết bị
- **Stock Shortage Report**: Parts cần order gấp
- **BOM Cost Analysis**: Chi phí parts cho từng machine type
- **Maintenance Schedule**: Lịch bảo trì sắp tới


## 📞 Hỗ trợ

Dự án được phát triển cho môi trường sản xuất thực phẩm với focus vào:
- Reliability và uptime cao
- Easy maintenance và troubleshooting  
- Integration với industrial equipment
- Vietnamese language support

---

**Phát triển bởi**: Maintenance Department  
**Môi trường**: Food Production Industry  
**Version**: 1.0  
**Last Updated**: September 2025
