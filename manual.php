<div x-data="{ showManual: false }" class="relative z-50">
    <button @click="showManual = true" class="fixed bottom-5 right-5 w-12 h-12 bg-white text-indigo-600 rounded-full shadow-xl border border-indigo-100 flex items-center justify-center hover:scale-110 transition-transform duration-200 z-50 group">
        <i class='bx bx-question-mark text-2xl group-hover:rotate-12 transition-transform'></i>
        <span class="absolute right-14 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none shadow-sm">คู่มือการใช้งาน</span>
    </button>

    <div x-show="showManual" x-cloak class="fixed inset-0 z-[60] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true" @click="showManual = false">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl w-full relative">
                
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 flex justify-between items-center">
                    <h3 class="text-lg leading-6 font-bold text-white flex items-center gap-2">
                        <i class='bx bxs-book-open'></i> คู่มือการใช้งาน (<?php echo ucfirst($_SESSION['role']); ?>)
                    </h3>
                    <button @click="showManual = false" class="text-white/80 hover:text-white transition"><i class='bx bx-x text-2xl'></i></button>
                </div>

                <div class="px-6 py-6 max-h-[70vh] overflow-y-auto bg-gray-50/50">
                    
                    <?php if($_SESSION['role'] == 'admin'): ?>
                    <div class="space-y-4">
                        <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex gap-4">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex-shrink-0 flex items-center justify-center font-bold text-lg">1</div>
                            <div>
                                <h4 class="font-bold text-slate-800">จัดการผู้ใช้ & ห้องเรียน</h4>
                                <p class="text-sm text-slate-600 mt-1">เมนูด้านซ้ายใช้สำหรับเพิ่ม/ลบ/แก้ไข ข้อมูลครู นักเรียน และโครงสร้างห้องเรียน รวมถึงการกำหนดสิทธิ์ผู้บริหาร</p>
                            </div>
                        </div>
                        <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex gap-4">
                            <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex-shrink-0 flex items-center justify-center font-bold text-lg">2</div>
                            <div>
                                <h4 class="font-bold text-slate-800">Import รายชื่อนักเรียน</h4>
                                <p class="text-sm text-slate-600 mt-1">เข้าเมนู <strong>"ห้องเรียน"</strong> > กดปุ่ม <strong>"รายชื่อนักเรียน"</strong> ของห้องเป้าหมาย > กด <strong>"Import CSV"</strong> เพื่อนำเข้าข้อมูลจำนวนมาก</p>
                            </div>
                        </div>
                        <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex gap-4">
                            <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex-shrink-0 flex items-center justify-center font-bold text-lg">3</div>
                            <div>
                                <h4 class="font-bold text-slate-800">Reset การตรวจ</h4>
                                <p class="text-sm text-slate-600 mt-1">เข้าเมนู <strong>"ประวัติการตรวจ"</strong> > ค้นหาห้องที่ต้องการ > กดปุ่ม <strong>"ลบ (Reset)"</strong> สีแดง เพื่อล้างข้อมูลการตรวจของวันนั้นทิ้งทั้งหมด (ใช้กรณีครูตรวจผิดวัน)</p>
                            </div>
                        </div>
                    </div>

                    <?php elseif($_SESSION['role'] == 'teacher'): ?>
                    <div class="space-y-4">
                        <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex gap-4">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex-shrink-0 flex items-center justify-center font-bold text-lg">1</div>
                            <div>
                                <h4 class="font-bold text-slate-800">เริ่มการตรวจ</h4>
                                <p class="text-sm text-slate-600 mt-1">ที่หน้า Dashboard ให้คลิกเลือก <strong>"ห้องเรียน"</strong> ที่ท่านต้องการตรวจระเบียบ</p>
                            </div>
                        </div>
                        <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex gap-4">
                            <div class="w-10 h-10 rounded-full bg-purple-100 text-purple-600 flex-shrink-0 flex items-center justify-center font-bold text-lg">2</div>
                            <div>
                                <h4 class="font-bold text-slate-800">บันทึกคะแนน</h4>
                                <p class="text-sm text-slate-600 mt-1">ติ๊กเลือกสถานะ <strong>"ผ่าน"</strong> หรือ <strong>"ไม่ผ่าน"</strong> (หากไม่ผ่านต้องระบุสาเหตุ) จากนั้นกดปุ่ม <strong>"บันทึกผลการตรวจ"</strong> ด้านล่างสุดและกดยืนยัน</p>
                            </div>
                        </div>
                        <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex gap-4">
                            <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex-shrink-0 flex items-center justify-center font-bold text-lg">3</div>
                            <div>
                                <h4 class="font-bold text-slate-800">แก้ไขข้อมูลย้อนหลัง</h4>
                                <p class="text-sm text-slate-600 mt-1">กดเมนู <strong>"ประวัติการตรวจ"</strong> ด้านบน > เลือกวันที่ > กดปุ่ม <strong>"แก้ไขข้อมูลวันเก่า"</strong> เพื่อปรับปรุงข้อมูล</p>
                            </div>
                        </div>
                    </div>

                    <?php else: ?>
                    <div class="space-y-4">
                        <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex gap-4">
                            <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex-shrink-0 flex items-center justify-center font-bold text-lg">1</div>
                            <div>
                                <h4 class="font-bold text-slate-800">ดูภาพรวม (Dashboard)</h4>
                                <p class="text-sm text-slate-600 mt-1">หน้าแรกจะแสดงกราฟสรุปยอดการมาเรียนและการผิดระเบียบประจำวันแบบ Real-time</p>
                            </div>
                        </div>
                        <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex gap-4">
                            <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex-shrink-0 flex items-center justify-center font-bold text-lg">2</div>
                            <div>
                                <h4 class="font-bold text-slate-800">Export รายงาน</h4>
                                <p class="text-sm text-slate-600 mt-1">กดเมนู <strong>"ประวัติการตรวจ"</strong> > เลือกห้องเรียนและวันที่ > กดปุ่ม <strong>"Export Excel"</strong> เพื่อดาวน์โหลดไฟล์รายงาน</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                
                <div class="bg-gray-50 px-6 py-4 flex justify-end">
                    <button @click="showManual = false" class="bg-white border border-gray-300 text-gray-700 font-bold py-2 px-6 rounded-xl hover:bg-gray-100 transition shadow-sm">
                        ปิดหน้าต่าง
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>