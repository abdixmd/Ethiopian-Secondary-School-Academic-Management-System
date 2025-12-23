<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$auth->requireRole(['admin', 'registrar']);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Custom Report Builder</h1>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="generate_custom.php" method="POST">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5 class="text-primary mb-3">1. Select Data Source</h5>
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="source" id="src_students" value="students" checked>
                            <label class="btn btn-outline-primary" for="src_students">Students</label>

                            <input type="radio" class="btn-check" name="source" id="src_teachers" value="teachers">
                            <label class="btn btn-outline-primary" for="src_teachers">Teachers</label>

                            <input type="radio" class="btn-check" name="source" id="src_finance" value="finance">
                            <label class="btn btn-outline-primary" for="src_finance">Finance</label>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5 class="text-primary mb-3">2. Select Fields</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fields[]" value="full_name" checked>
                                    <label class="form-check-label">Full Name</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fields[]" value="grade_level" checked>
                                    <label class="form-check-label">Grade Level</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fields[]" value="gender">
                                    <label class="form-check-label">Gender</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fields[]" value="dob">
                                    <label class="form-check-label">Date of Birth</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fields[]" value="parent_phone">
                                    <label class="form-check-label">Parent Phone</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fields[]" value="address">
                                    <label class="form-check-label">Address</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5 class="text-primary mb-3">3. Filters</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Grade Level</label>
                                <select name="filter_grade" class="form-select">
                                    <option value="">All</option>
                                    <option value="9">Grade 9</option>
                                    <option value="10">Grade 10</option>
                                    <option value="11">Grade 11</option>
                                    <option value="12">Grade 12</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gender</label>
                                <select name="filter_gender" class="form-select">
                                    <option value="">All</option>
                                    <option value="M">Male</option>
                                    <option value="F">Female</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="filter_status" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="all">All</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-file-export me-2"></i> Generate Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>