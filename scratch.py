import re

with open('q:/SharingFood/resources/js/Pages/Welcome.vue', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Remove "Không bán hoặc chia sẻ dữ liệu của tôi"
content = re.sub(r'<li><a href="#">Không bán hoặc chia sẻ dữ liệu của tôi</a></li>\s*', '', content)
content = re.sub(r'<li><a href="#" class="[^"]*">Không bán hoặc chia sẻ dữ liệu của tôi</a></li>\s*', '', content)

# 2. Fix pointer events to allow clicking the Real Footer
# Main wrapper
content = content.replace(
    '<div class="min-h-screen bg-transparent font-sans text-gray-900 selection:bg-emerald-100 selection:text-emerald-900">',
    '<div class="min-h-screen bg-transparent font-sans text-gray-900 selection:bg-emerald-100 selection:text-emerald-900 pointer-events-none">'
)

# Navbar
content = content.replace(
    '<nav class="border-b border-gray-100',
    '<nav class="pointer-events-auto border-b border-gray-100'
)

# Hero Section
content = content.replace(
    '<section class="relative bg-white pt-20',
    '<section class="pointer-events-auto relative bg-white pt-20'
)

# Section 2
content = content.replace(
    '<section class="py-32 bg-[#0c4a34] overflow-hidden relative">',
    '<section class="pointer-events-auto py-32 bg-[#0c4a34] overflow-hidden relative">'
)

# Section 3
content = content.replace(
    '<section class="py-32 bg-white relative overflow-hidden">',
    '<section class="pointer-events-auto py-32 bg-white relative overflow-hidden">'
)

# Menu Block
content = content.replace(
    '<div class="w-full relative z-30 bg-emerald-600',
    '<div class="pointer-events-auto w-full relative z-30 bg-emerald-600'
)

# Real Footer (just to make sure it has pointer-events-auto on its inner links container if needed, but it should naturally inherit from document body since it's outside the min-h-screen)
# Actually, wait. Real footer has pointer-events-auto on the copyright div already: `pointer-events-auto`
# So we are good.

with open('q:/SharingFood/resources/js/Pages/Welcome.vue', 'w', encoding='utf-8') as f:
    f.write(content)

print("Replacement successful")
