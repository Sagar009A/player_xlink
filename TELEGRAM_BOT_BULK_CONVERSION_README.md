# Telegram Bot - Bulk Link Conversion Feature

## 🎉 Naye Features

### 1. **Bulk Link Conversion (Ek saath 20 links tak)**
- Ab aap ek message me **maximum 20 links** bhej sakte hain
- Bot sabko automatically convert karega
- Ek se zyada link detect hone pe bulk mode activate hoga

### 2. **Live Progress Tracking**
Bot conversion ke dauran real-time updates dega:
- **Total Links**: Kitne links convert karne hain
- **Processing**: Abhi kitna process ho raha hai
- **Post-processing**: Kitna post-processing me hai (live display)
- **Completed**: Kitne successfully convert ho gaye
- **Failed**: Kitne fail hue

### 3. **Automatic Video Title Fetching**
- Bot ab automatically video ka title fetch karega
- Terabox, YouTube aur dusre platforms ke liye title support
- Har converted link ke saath title dikhega
- Platform name bhi show hoga (jaise [Terabox], [YouTube])

## 📱 Kaise Use Karein

### Single Link Conversion:
```
Simply ek link send kar do:
https://terabox.com/s/example123
```

### Bulk Link Conversion (Maximum 20):
```
Multiple links ek saath send kar do:

https://terabox.com/s/example1
https://terabox.com/s/example2
https://terabox.com/s/example3
...
(up to 20 links)
```

## 🔄 Conversion Process

1. **Start**: Bot links detect karta hai
2. **Progress Updates**: 
   - "⏳ Processing: 3/10"
   - "🔨 Post-processing link #3..."
   - "✅ Completed: 2"
   - "❌ Failed: 0"
3. **Completion**: Summary ke saath sare converted links

## 📊 Result Summary

Conversion ke baad aapko milega:

```
✅ Bulk Conversion Completed!

📊 Summary:
• Total: 10 links
• ✅ Success: 8
• ❌ Failed: 2

🎉 Converted Links:

#1. Video Title 1 [Terabox]
🔗 https://yoursite.com/abc123

#2. Video Title 2 [YouTube]
🔗 https://yoursite.com/xyz456

... aur baaki links
```

## ⚙️ Technical Details

### Maximum Limits:
- **Bot Requests**: 20 links per conversion
- **Web Interface**: 100 links per conversion
- **Timeout**: 30 seconds per link

### Title Fetching:
- Terabox: Automatic title extraction via API
- YouTube: Video title from metadata
- Other platforms: Default title with timestamp

### Progress Display:
- Real-time message updates
- No flooding - efficient message editing
- Clear status indicators

## 🚀 Updated Commands

### `/help` - Ab enhanced hai:
- Bulk conversion ka mention
- Video title fetching ka info
- Live progress tracking ka note

### `/start` - Welcome message updated:
- Bulk conversion feature highlight
- Maximum 20 links ki limit

## 💡 Tips

1. **Best Practice**: 5-10 links bhejne se best performance milegi
2. **Title Fetching**: Thoda time lagta hai title fetch karne me
3. **Failed Links**: Agar kuch fail ho, individual try kar sakte hain
4. **Progress**: Message automatically update hota rahega

## 🔧 Files Modified

1. **telegram_bot/TelegramBot.php**
   - `handlePostConversion()` - Bulk detection logic
   - `handleBulkConversion()` - New bulk handler
   - `convertLinkWithTitle()` - Title fetching integration
   - `handleHelp()` - Updated help text
   - `handleStart()` - Updated welcome message

2. **api/bulk_converter.php**
   - Max limit: 20 for bot, 100 for web
   - User-agent based detection

3. **api/bot_api.php**
   - Auto-extraction enabled
   - Title return in response
   - Platform info included

## 📝 Example Usage

### Scenario 1: Terabox Links
```
Send to bot:
https://terabox.com/s/abc123
https://terabox.com/s/def456
https://terabox.com/s/ghi789

Result:
#1. Movie Name 1080p [Terabox]
🔗 https://yoursite.com/short1

#2. Series Episode 2 [Terabox]
🔗 https://yoursite.com/short2

#3. Documentary HD [Terabox]
🔗 https://yoursite.com/short3
```

### Scenario 2: Mixed Platforms
```
Send to bot:
https://terabox.com/s/abc123
https://youtube.com/watch?v=xyz
https://gofile.io/d/abc123

Result:
Sab platforms ka title automatic fetch hoga!
```

## ✅ Testing Checklist

- [x] Single link conversion working
- [x] Bulk link detection (2+ links)
- [x] Maximum 20 links enforcement
- [x] Progress updates during conversion
- [x] Post-processing status display
- [x] Video title fetching
- [x] Platform name display
- [x] Summary with success/failed count
- [x] Help text updated
- [x] Welcome message updated

## 🎯 Future Enhancements

Agle updates me add kar sakte hain:
- Custom templates per link
- Scheduled bulk conversions
- Bulk conversion history
- Export links as file
- Folder organization

---

**Developed with ❤️ for efficient bulk link management**

**Need help?** Use `/help` command in bot for latest info!
