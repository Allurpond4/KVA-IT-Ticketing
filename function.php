<DOCUMENT>
    <script>
        function confirmDelete(issueId) {
            Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                text: "คุณต้องการลบรายการนี้หรือไม่?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'delete_request.php?id=' + issueId;
                }
            });
        }
    </script>

    <script>
        function completeTask(issueId) {
            Swal.fire({
                title: 'กรอกรายละเอียดการเสร็จงาน',
                input: 'textarea',
                inputPlaceholder: 'กรอกรายละเอียดที่นี่...',
                showCancelButton: true,
                confirmButtonText: 'บันทึก',
                cancelButtonText: 'ยกเลิก',
                preConfirm: (detail) => {
                    Swal.fire({
                        title: 'กำลังบันทึก...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    return fetch('complete.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                issue_id: issueId,
                                detail: detail,
                            })
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            Swal.close(); // ปิด loading
                            if (data.success) {
                                console.log('User Email:', data.email);
                                Swal.fire({
                                    title: 'สำเร็จ!',
                                    text: 'การแจ้งซ่อมเสร็จสิ้นแล้ว',
                                    icon: 'success',
                                    confirmButtonText: 'ตกลง'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'เกิดข้อผิดพลาด!',
                                    text: data.error,
                                    icon: 'error',
                                    confirmButtonText: 'ตกลง'
                                });
                            }
                        })
                        .catch(error => {
                            Swal.close(); // ปิด loading
                            console.error('Error:', error);
                            Swal.fire({
                                title: 'เกิดข้อผิดพลาด!',
                                text: 'ไม่สามารถบันทึกข้อมูลได้',
                                icon: 'error',
                                confirmButtonText: 'ตกลง'
                            });
                        });
                }
            });
        }
    </script>

    <script>
        function showDetails(issueId, userType) {
            console.log('Fetching details for issue ID:', issueId);
            Swal.fire({
                title: 'กำลังโหลดข้อมูล...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('get_request_details.php?id=' + issueId)
                .then(response => response.json())
                .then(data => {
                    Swal.close(); // ปิด loading
                    console.log('Response data:', data);
                    if (data.error) {
                        Swal.fire({
                            title: 'เกิดข้อผิดพลาด!',
                            text: data.error,
                            icon: 'error',
                            confirmButtonText: 'ตกลง'
                        });
                    } else {
                        const created_at = data.created_at || 'รอดำเนินการ';
                        const department = data.department;
                        const requester = data.requester;
                        const title = data.title;
                        const description = data.description || 'ไม่มีรายละเอียด';
                        const priority = data.priority;
                        const operator = data.operator || 'รอดำเนินการ';
                        const status = data.status || 'รอดำเนินการ';
                        const plan_date = data.plan_date || 'รอดำเนินการ';
                        const completed_at = data.completed_at || 'รอดำเนินการ';
                        const detail = data.detail || 'ไม่มีรายละเอียด';
                        const contact = data.contact || 'ไม่มีรายละเอียด';
                        const attachment = data.attachment ? `<a href="download.php?file=uploads/${data.attachment}" target="_blank" style="color: blue; text-decoration: underline;">ดาวน์โหลดไฟล์แนบ</a>` : 'ไม่มีไฟล์แนบ';

                        let htmlContent = `
                        <p style="text-align: left;"><strong>วันที่แจ้ง/Request Date:</strong> ${created_at}</p>
                        <p style="text-align: left;"><strong>แผนก/Department:</strong> ${department}</p>
                        <p style="text-align: left;"><strong>ผู้แจ้ง/Requester:</strong> ${requester}</p>
                        <p style="text-align: left;"><strong>ปัญหาที่เจอ/Issue:</strong> ${title}</p>
                        <p style="text-align: left;"><strong>รายละเอียดของปัญหา/Issue Detail:</strong> ${description}</p>
                        <p style="text-align: left;"><strong>Priority:</strong> ${priority}</p>
                        <p style="text-align: left;"><strong>Status:</strong> ${status}</p>
                        <p style="text-align: left;"><strong>Attachment:</strong> ${attachment}</p>
                        <p style="text-align: left;"><strong>Contact :</strong> ${contact}</p>
                        <hr style="margin-top: 20px; margin-bottom: 20px;">
                        <p style="text-align: left;"><strong>IT:</strong> ${operator}</p>
                        <p style="text-align: left;"><strong>Plan Date:</strong> ${plan_date}</p>
                        <p style="text-align: left;"><strong>วิธีแก้ไข/Resolved:</strong> ${detail}</p>
                        <hr style="margin-top: 20px; margin-bottom: 20px;">
                        <p style="text-align: left;"><strong>วันที่เสร็จComplete Date:</strong> ${completed_at}</p>
                    `;
                        Swal.fire({
                            title: 'รายละเอียดการแจ้งซ่อม',
                            html: htmlContent,
                            icon: 'info',
                            confirmButtonText: 'ตกลง'
                        });
                    }
                })
                .catch(error => {
                    Swal.close(); // ปิด loading
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'เกิดข้อผิดพลาด!',
                        text: 'ไม่สามารถดึงข้อมูลได้',
                        icon: 'error',
                        confirmButtonText: 'ตกลง'
                    });
                });
        }
    </script>

    <script>
       function acceptRequest(issueId) {

fetch('get_sla_date.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({ issue_id: issueId })
})
.then(res => res.json())
.then(sla => {

    Swal.fire({
        title: 'กำหนด Promise Date',
        html: 'Priority : <b>' + sla.priority + '</b>',
        input: 'datetime-local',
        inputValue: sla.plan_date,
        inputLabel: 'วันที่คาดว่าจะเสร็จ',
        showCancelButton: true,
        confirmButtonText: 'รับงาน',
        cancelButtonText: 'ยกเลิก',
        preConfirm: (value) => {

            if (!value) {
                Swal.showValidationMessage('กรุณาเลือกวันที่');
            }

            return value;
        }

    }).then((result) => {

        if (!result.isConfirmed) return;

        Swal.fire({
            title: 'กำลังบันทึก...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('accept.php', {

            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                issue_id: issueId,
                completion_date: result.value
            })

        })
        .then(res => res.json())
        .then(data => {

            Swal.close();

            if (data.success) {

                Swal.fire({
                    icon: 'success',
                    title: 'รับงานสำเร็จ'
                }).then(() => location.reload());

            } else {

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.error
                });

            }

        });

    });

});

}
    </script>

    <script>
        function confirmLogout() {
            Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                text: "คุณต้องการออกจากระบบหรือไม่?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ใช่, ออกจากระบบ!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }

        function toggleDropdown() {
            var dropdownMenu = document.getElementById('dropdownMenu');
            dropdownMenu.classList.toggle('hidden');
        }

        function toggleMobileMenu() {
            var mobileMenu = document.getElementById('mobileMenu');
            mobileMenu.classList.toggle('hidden');
        }
    </script>

    <script>
        function validateForm() {
            const requester = document.getElementById('requester').value;
            if (requester === '') {
                Swal.fire({
                    title: 'ข้อผิดพลาด!',
                    text: 'กรุณาเลือก Requester',
                    icon: 'error',
                    confirmButtonText: 'ตกลง'
                });
                return false;
            }
            return true;
        }
    </script>

    <script>
        function validatePassword(password) {
            const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
            return regex.test(password);
        }

        function validateForm() {
            const password = document.getElementById('reg_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (!validatePassword(password)) {
                Swal.fire({
                    title: 'ข้อผิดพลาด!',
                    text: 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร ประกอบด้วยตัวอักษรใหญ่ ตัวอักษรเล็ก ตัวเลข และสัญลักษณ์พิเศษ',
                    icon: 'error',
                    confirmButtonText: 'ตกลง'
                });
                return false;
            }

            if (password !== confirmPassword) {
                Swal.fire({
                    title: 'ข้อผิดพลาด!',
                    text: 'รหัสผ่านไม่ตรงกัน',
                    icon: 'error',
                    confirmButtonText: 'ตกลง'
                });
                return false;
            }

            return true;
        }
    </script>
</DOCUMENT>