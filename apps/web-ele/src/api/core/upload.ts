import { requestClient } from '#/api/request';

export interface UploadFileParams {
  file: File;
  onError?: (error: Error) => void;
  onProgress?: (progress: { percent: number }) => void;
  onSuccess?: (data: any, file: File) => void;
}

export interface UploadResult {
  name: string;
  size: number;
  /** 站内相对地址，如 /storage/uploads/20261017/xxx.png */
  url: string;
}

/**
 * 通用图片上传（头像等）。
 *
 * 走 requestClient，自动带上 Authorization；可直接作为 ElUpload 的 http-request 使用。
 */
export async function uploadFile({
  file,
  onError,
  onProgress,
  onSuccess,
}: UploadFileParams) {
  try {
    onProgress?.({ percent: 0 });

    const data = await requestClient.upload<UploadResult>('/upload', { file });

    onProgress?.({ percent: 100 });
    onSuccess?.(data, file);
  } catch (error) {
    onError?.(error instanceof Error ? error : new Error(String(error)));
  }
}
